<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\ItemPrice;
use ChargeBee\ChargeBee\Models\PaymentSource;
use ChargeBee\ChargeBee\Models\Subscription as ChargebeeSubscription;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Laravel\CashierChargebee\Concerns\AllowsCoupons;
use Laravel\CashierChargebee\Concerns\HandlesTaxes;
use Laravel\CashierChargebee\Concerns\Prorates;

class SubscriptionBuilder
{
    use AllowsCoupons;
    use Conditionable;
    use HandlesTaxes;
    use Prorates;

    /**
     * The model that is subscribing.
     *
     * @var \Laravel\CashierChargebee\Billable|\Illuminate\Database\Eloquent\Model
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
     * The date on which the billing cycle should be anchored.
     *
     * @var int|null
     */
    protected $billingCycleAnchor = null;

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
        $options = is_array($price) ? $price : ['itemPriceId' => $price];

        $quantity = $price['quantity'] ?? $quantity;

        if (! is_null($quantity)) {
            $options['quantity'] = $quantity;
        }

        if (isset($options['itemPriceId'])) {
            $this->items[$options['itemPriceId']] = $options;
        } else {
            $this->items[] = $options;
        }

        return $this;
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

        $chargebeeSubscription = ChargebeeSubscription::createWithItems($chargebeeCustomer->id, array_merge(
            $this->buildPayload(),
            $subscriptionOptions
        ));

        $subscription = $this->createSubscription($chargebeeSubscription->subscription());

        return $subscription;
    }

    /**
     * Create the Eloquent Subscription.
     * 
     * @todo Consult chargebee_id on item
     */
    protected function createSubscription(ChargebeeSubscription $chargebeeSubscription): Subscription
    {
        if ($subscription = $this->owner->subscriptions()->where('chargebee_id', $chargebeeSubscription->id)->first()) {
            return $subscription;
        }

        $firstItem = $chargebeeSubscription->subscriptionItems[0];
        $isSinglePrice = count($chargebeeSubscription->subscriptionItems) === 1;

        $subscription = $this->owner->subscriptions()->create([
            'type' => $this->type,
            'chargebee_id' => $chargebeeSubscription->id,
            'chargebee_status' => $chargebeeSubscription->status,
            'chargebee_price' => $isSinglePrice ? $firstItem->itemPriceId : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'trial_ends_at' => ! $this->skipTrial ? $this->trialExpires : null,
            'ends_at' => null,
        ]);

        foreach ($chargebeeSubscription->subscriptionItems as $item) {
            $price = ItemPrice::retrieve($item->itemPriceId)->itemPrice();
            $subscription->items()->create([
                'chargebee_id' => $price->itemId,
                'chargebee_product' => $price->itemId,
                'chargebee_price' => $item->itemPriceId,
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
     * 
     * @todo Clarify startDate
     */
    protected function buildPayload(): array
    {
        $payload = array_filter([
            'couponIds' => $this->couponIds,
            'metaData' => $this->metadata,
            'subscriptionItems' => Collection::make($this->items)->values()->all(),
            'trialEnd' => $this->getTrialEndForPayload(),
            'autoCollection' => 'off',
        ]);

        return $payload;
    }

    /**
     * Get the trial ending date for the Chargebee payload.
     */
    protected function getTrialEndForPayload(): int
    {
        if ($this->trialExpires) {
            return $this->trialExpires->getTimestamp();
        }

        return 0;
    }
}
