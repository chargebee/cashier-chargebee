<?php

namespace Chargebee\Cashier\Listeners;

use Carbon\Carbon;
use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Events\WebhookReceived;
use ChargeBee\ChargeBee\Models\ItemPrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HandleWebhookReceived
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $eventType = $event->payload['event_type'] ?? null;

        if (! $eventType) {
            Log::warning('WebhookReceived: Missing event_type in payload.', $event->payload);

            return;
        }

        $handlerMethod = $this->getHandlerMethod($eventType);

        if (method_exists($this, $handlerMethod)) {
            $this->{$handlerMethod}($event->payload);
        } else {
            Log::info("WebhookReceived: No handler found for event_type: {$eventType}", $event->payload);
        }
    }

    /**
     * Get the handler method name for a given event type.
     */
    protected function getHandlerMethod(string $eventType): string
    {
        return 'handle'.Str::studly(str_replace('_', '', $eventType));
    }

    /**
     * Handle the customer_deleted event.
     */
    protected function handleCustomerDeleted(array $payload): void
    {
        if ($user = Cashier::findBillable($payload['content']['customer']['id'])) {
            $user->forceFill([
                'chargebee_id' => null,
                'trial_ends_at' => null,
                'pm_type' => null,
                'pm_last_four' => null,
            ])->save();

            Log::info('Customer deleted successfully.', [
                'customer_id' => $payload['content']['customer']['id'],
                'user_id' => $user->id,
            ]);
        } else {
            Log::info('Customer deletion attempted, but no matching user found.', [
                'customer_id' => $payload['content']['customer']['id'],
            ]);
        }
    }

    /**
     * Handle the customer_changed event.
     */
    protected function handleCustomerChanged(array $payload): void
    {
        if ($user = Cashier::findBillable($payload['content']['customer']['id'])) {
            $user->updateCustomerFromChargebee();
            $user->updateDefaultPaymentMethodFromChargebee();

            Log::info('Customer updated successfully.', [
                'customer_id' => $payload['content']['customer']['id'],
                'user_id' => $user->id,
            ]);
        } else {
            Log::info('Customer update attempted, but no matching user found.', [
                'customer_id' => $payload['content']['customer']['id'],
            ]);
        }
    }

    /**
     * Handle the subscription_created event.
     */
    protected function handleSubscriptionCreated(array $payload): void
    {
        if ($user = Cashier::findBillable($payload['content']['subscription']['customer_id'])) {
            if (! $user->subscriptions->contains('chargebee_id', $payload['content']['subscription']['id'])) {
                $subscription = $this->updateOrCreateSubscriptionFromPayload($user, $payload['content']['subscription']);

                Log::info('Subscription created successfully.', [
                    'subscription_id' => $subscription->id,
                    'chargebee_subscription_id' => $payload['content']['subscription']['id'],
                ]);
            } else {
                $subscription = $user->subscriptions()->where('chargebee_id', $payload['content']['subscription']['id'])->first();

                Log::info('Subscription creation attempted, but subscription already exists.', [
                    'subscription_id' => $subscription->id,
                    'chargebee_subscription_id' => $payload['content']['subscription']['id'],
                ]);
            }

            if (! is_null($user->trial_ends_at)) {
                $user->trial_ends_at = null;
                $user->save();
            }
        } else {
            Log::info('Subscription creation for a customer attempted, but no matching user found.', [
                'customer_id' => $payload['content']['subscription']['customer_id'],
            ]);
        }
    }

    /**
     * Handle the subscription_changed event.
     */
    protected function handleSubscriptionChanged(array $payload): void
    {
        if ($user = Cashier::findBillable($payload['content']['subscription']['customer_id'])) {
            $subscription = $this->updateOrCreateSubscriptionFromPayload($user, $payload['content']['subscription']);

            Log::info('Subscription updated successfully.', [
                'subscription_id' => $subscription->id,
                'chargebee_subscription_id' => $payload['content']['subscription']['id'],
            ]);
        } else {
            Log::info('Subscription update attempted, but no matching user found.', [
                'customer_id' => $payload['content']['subscription']['customer_id'],
            ]);
        }
    }

    /**
     * Handle the subscription_renewed event.
     */
    protected function handleSubscriptionRenewed(array $payload): void
    {
        if ($user = Cashier::findBillable($payload['content']['subscription']['customer_id'])) {
            $subscription = $this->updateOrCreateSubscriptionFromPayload($user, $payload['content']['subscription']);

            Log::info('Subscription renewed successfully.', [
                'subscription_id' => $subscription->id,
                'chargebee_subscription_id' => $payload['content']['subscription']['id'],
            ]);
        } else {
            Log::info('Subscription renewal attempted, but no matching user found.', [
                'customer_id' => $payload['content']['subscription']['customer_id'],
            ]);
        }
    }

    /**
     * Create or update a subscription from Chargebee webhook payload.
     */
    protected function updateOrCreateSubscriptionFromPayload($user, array $data)
    {
        $firstItem = $data['subscription_items'][0];
        $isSinglePrice = count($data['subscription_items']) === 1;

        $trialEndsAt = isset($data['trial_end']) ? Carbon::createFromTimestamp($data['trial_end']) : null;
        $endsAt = isset($data['cancelled_at']) ? Carbon::createFromTimestamp($data['cancelled_at']) : null;

        $subscription = $user->subscriptions()->updateOrCreate(
            ['chargebee_id' => $data['id']],
            [
                'type' => $data['meta_data']['type'] ?? $data['meta_data']['name'] ?? $this->newSubscriptionType($data),
                'chargebee_status' => $data['status'],
                'chargebee_price' => $isSinglePrice ? $firstItem['item_price_id'] : null,
                'quantity' => $isSinglePrice && isset($firstItem['quantity']) ? $firstItem['quantity'] : null,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => $endsAt,
            ]
        );

        $subscriptionItemPriceIds = [];

        foreach ($data['subscription_items'] as $item) {
            $subscriptionItemPriceIds[] = $item['item_price_id'];

            $subscription->items()->updateOrCreate(
                ['chargebee_price' => $item['item_price_id']],
                [
                    'chargebee_product' => ItemPrice::retrieve($item['item_price_id'])->itemPrice()->itemId,
                    'quantity' => $item['quantity'] ?? null,
                ]
            );
        }

        $subscription->items()->whereNotIn('chargebee_price', $subscriptionItemPriceIds)->delete();

        return $subscription;
    }

    /**
     * Determines the type that should be used when new subscriptions are created from the Chargebee dashboard.
     */
    protected function newSubscriptionType(array $payload): string
    {
        return 'default';
    }
}
