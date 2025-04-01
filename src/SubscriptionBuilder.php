<?php

namespace Chargebee\Cashier;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Chargebee\Cashier\Concerns\AllowsCoupons;
use Chargebee\Cashier\Concerns\HandlesTaxes;
use Chargebee\Cashier\Concerns\Prorates;
use Chargebee\Resources\Customer\Customer;
use Chargebee\Resources\PaymentSource\PaymentSource;
use Chargebee\Resources\Subscription\Subscription as ChargebeeSubscription;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;

class SubscriptionBuilder
{
    use AllowsCoupons;
    use Conditionable;
    use HandlesTaxes;
    use Prorates;

    /**
     * The model that is subscribing.
     *
     * @var \Chargebee\Cashier\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The type of the subscription.
     *
     * @var string
     */
    protected $type;

    /**
     * The prices the customer is being subscribed to.
     *
     * @var array
     */
    protected $items = [];

    /**
     * The date and time the trial will expire.
     *
     * @var \Carbon\Carbon|\Carbon\CarbonInterface|null
     */
    protected $trialExpires;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array
     */
    protected $metadata = [];

    /**
     * Create a new subscription builder instance.
     */
    public function __construct(mixed $owner, string $type, string|array $prices = [])
    {
        $this->type = $type;
        $this->owner = $owner;

        foreach ((array) $prices as $price) {
            $this->price($price);
        }
    }

    /**
     * Set a price on the subscription builder.
     */
    public function price(string|array $price, ?int $quantity = 1): static
    {
        $options = is_array($price) ? $price : ['item_price_id' => $price];

        $quantity = $price['quantity'] ?? $quantity;

        if (! is_null($quantity)) {
            $options['quantity'] = $quantity;
        }

        if (! isset($options['item_price_id'])) {
            throw new InvalidArgumentException('Each price must include an "item_price_id" key.');
        }

        $this->items[$options['item_price_id']] = $options;

        return $this;
    }

    /**
     * Set a metered price on the subscription builder.
     */
    public function meteredPrice(string $price): static
    {
        return $this->price($price, null);
    }

    /**
     * Specify the quantity of a subscription item.
     */
    public function quantity(?int $quantity, ?string $price = null): static
    {
        if (is_null($price)) {
            if (count($this->items) > 1) {
                throw new InvalidArgumentException('Price is required when creating subscriptions with multiple prices.');
            }

            $price = Arr::first($this->items)['item_price_id'];
        }

        return $this->price($price, $quantity);
    }

    /**
     * Specify the number of days of the trial.
     */
    public function trialDays(int $trialDays): static
    {
        $this->trialExpires = Carbon::now()->addDays($trialDays);

        return $this;
    }

    /**
     * Specify the ending date of the trial.
     */
    public function trialUntil(Carbon|CarbonInterface $trialUntil): static
    {
        $this->trialExpires = $trialUntil;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     */
    public function skipTrial(): static
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * The metadata to apply to a new subscription.
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = (array) $metadata;

        return $this;
    }

    /**
     * Add a new Chargebee subscription to the Chargebee model.
     */
    public function add(array $customerOptions = [], array $subscriptionOptions = []): Subscription
    {
        return $this->create(null, $customerOptions, $subscriptionOptions);
    }

    /**
     * Create a new Chargebee subscription.
     *
     * @throws \Exception
     */
    public function create(PaymentSource|string|null $paymentSource = null, array $customerOptions = [], array $subscriptionOptions = []): Subscription
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        $chargebeeCustomer = $this->getChargebeeCustomer($paymentSource, $customerOptions);
        $chargebee = Cashier::chargebee();
        $chargebeeSubscription = $chargebee->subscription()->createWithItems($chargebeeCustomer->id, array_merge(
            $this->buildPayload(),
            $subscriptionOptions
        ));

        return $this->createSubscription($chargebeeSubscription->subscription);
    }

    /**
     * Create the Eloquent Subscription.
     */
    protected function createSubscription(ChargebeeSubscription $chargebeeSubscription): Subscription
    {
        if ($subscription = $this->owner->subscriptions()->where('chargebee_id', $chargebeeSubscription->id)->first()) {
            return $subscription;
        }

        $firstItem = $chargebeeSubscription->subscription_items[0];
        $isSinglePrice = count($chargebeeSubscription->subscription_items) === 1;

        $subscription = $this->owner->subscriptions()->create([
            'type' => $this->type,
            'chargebee_id' => $chargebeeSubscription->id,
            'chargebee_status' => $chargebeeSubscription->status->value,
            'chargebee_price' => $isSinglePrice ? $firstItem->item_price_id : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'trial_ends_at' => $chargebeeSubscription->trial_end ?? null,
            'ends_at' => null,
        ]);
        $chargebee = Cashier::chargebee();
        foreach ($chargebeeSubscription->subscription_items as $item) {
            $subscription->items()->create([
                'chargebee_product' => $chargebee->itemPrice()->retrieve($item->item_price_id)->item_price->item_id,
                'chargebee_price' => $item->item_price_id,
                'quantity' => $item->quantity ?? null,
            ]);
        }

        return $subscription;
    }

    /**
     * Get the Chargebee customer instance for the current user and payment source.
     */
    protected function getChargebeeCustomer(PaymentSource|string|null $paymentSource = null, array $options = []): Customer
    {
        $customer = $this->owner->createOrGetChargebeeCustomer($options);

        if ($paymentSource) {
            $this->owner->updateDefaultPaymentMethod($paymentSource);
        }

        return $customer;
    }

    /**
     * Build the payload for subscription creation.
     */
    protected function buildPayload(): array
    {
        $payload = array_filter([
            'couponIds' => $this->couponIds,
            'subscriptionItems' => Collection::make($this->items)->values()->all(),
            'trialEnd' => $this->getTrialEndForPayload(),
            'autoCollection' => 'off',
        ], fn ($value) => ! is_null($value));

        if (! empty($this->metadata)) {
            $payload['meta_data'] = json_encode($this->metadata);
        }

        return $payload;
    }

    /**
     * Get the trial ending date for the Chargebee payload.
     */
    protected function getTrialEndForPayload(): int|null
    {
        if ($this->skipTrial) {
            return 0;
        }

        if ($this->trialExpires) {
            return $this->trialExpires->getTimestamp();
        }

        return null;
    }

    /**
     * Get the price tax rates for the Chargebee payload.
     *
     * @param  string  $price
     * @return array|null
     */
    protected function getPriceTaxRatesForPayload($price): array|null
    {
        if ($taxRates = $this->owner->priceTaxRates()) {
            return $taxRates[$price] ?? null;
        }

        return null;
    }

    /**
     * Begin a new Checkout Session.
     *
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Chargebee\Cashier\Checkout
     */
    public function checkout(array $sessionOptions = [], array $customerOptions = []): Checkout
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        if (! $this->skipTrial && $this->trialExpires) {
            $minimumTrialPeriod = Carbon::now()->addHours(48)->addSeconds(10);

            $trialEnd = $this->trialExpires->gt($minimumTrialPeriod) ? $this->trialExpires : $minimumTrialPeriod;
        } else {
            $trialEnd = null;
        }

        $payload = array_filter([
            'subscription_items' => Collection::make($this->items)->values()->all(),
            'mode' => Session::MODE_SUBSCRIPTION,
            'subscription' => array_filter([
                'trial_end' => $trialEnd ? $trialEnd->getTimestamp() : null,
                'meta_data' => array_merge($this->metadata, [
                    'name' => $this->type,
                    'type' => $this->type,
                ]),
            ]),
        ]);

        return Checkout::customer($this->owner, $this)
            ->create([], array_merge_recursive($payload, $sessionOptions), $customerOptions);
    }

    /*
     * Get the items set on the subscription builder.
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
