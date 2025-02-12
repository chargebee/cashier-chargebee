<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\ItemPrice;
use ChargeBee\ChargeBee\Models\SubscriptionSubscriptionItem;
use ChargeBee\ChargeBee\Models\Usage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Laravel\CashierChargebee\Concerns\Prorates;
use Laravel\CashierChargebee\Database\Factories\SubscriptionItemFactory;

class SubscriptionItem extends Model
{
    use HasFactory, Prorates;
    
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription that the item belongs to.
     */
    public function subscription(): BelongsTo
    {
        $model = Cashier::$subscriptionModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Increment the quantity of the subscription item.
     */
    public function incrementQuantity(int $count = 1, bool $invoiceImmediately = false): static
    {
        $this->updateQuantity($this->quantity + $count, $invoiceImmediately);

        return $this;
    }

    /**
     *  Increment the quantity of the subscription item, and invoice immediately.
     */
    public function incrementAndInvoice(int $count = 1): static
    {
        $this->incrementQuantity($count, true);

        return $this;
    }

    /**
     * Decrement the quantity of the subscription item.
     */
    public function decrementQuantity(int $count = 1): static
    {
        $this->updateQuantity(max(1, $this->quantity - $count));

        return $this;
    }

    /**
     * Update the quantity of the subscription item.
     */
    public function updateQuantity(int $quantity, bool $invoiceImmediately = false): static
    {
        $this->subscription->updateQuantity($quantity, $this->chargebee_price, $invoiceImmediately);

        return $this;
    }

    /**
     * Swap the subscription item to a new Chargebee price.
     */
    public function swap(string $price, array $itemOptions = [], array $subscriptionOptions = []): static
    {
        $item = ['itemPriceId' => $price];
        if ($this->quantity) {
            $item['quantity'] = $this->quantity;
        }

        $itemOptions = array_merge($item, $itemOptions);

        if (! is_null($this->prorateBehavior())) {
            $subscriptionOptions = array_merge(['prorate' => $this->prorateBehavior()], $subscriptionOptions);
        }

        $chargebeeSubscriptionItem = $this->updateChargebeeSubscriptionItem($itemOptions, $subscriptionOptions);

        $priceDetails = ItemPrice::retrieve($price)->itemPrice();
        $this->fill([
            'chargebee_product' => $priceDetails->itemId,
            'chargebee_price' => $chargebeeSubscriptionItem->itemPriceId,
            'quantity' => $chargebeeSubscriptionItem->quantity,
        ])->save();

        if ($this->subscription->hasSinglePrice()) {
            $this->subscription->fill([
                'chargebee_price' => $price,
                'quantity' => $chargebeeSubscriptionItem->quantity,
            ]);
        }

        $chargebeeSubscription = $this->subscription->asChargebeeSubscription();

        $this->subscription->fill([
            'chargebee_status' => $chargebeeSubscription->status,
        ])->save();

        return $this;
    }

    /**
     * Swap the subscription item to a new Chargebee price, and invoice immediately.
     */
    public function swapAndInvoice(string $price, array $itemOptions = [], array $subscriptionOptions = []): static
    {
        $subscriptionOptions['invoiceImmediately'] = true;

        return $this->swap($price, $itemOptions, $subscriptionOptions);
    }

    /**
     * Report usage for a metered product.
     */
    public function reportUsage(int $quantity = 1, DateTimeInterface|int|null $timestamp = null): Usage
    {
        $timestamp = $timestamp instanceof DateTimeInterface ? $timestamp->getTimestamp() : $timestamp;

        $result = Usage::create($this->subscription->chargebee_id, [
            'itemPriceId' => $this->chargebee_price,
            'quantity' => $quantity,
            'usageDate' => $timestamp ?? time(),
        ]);

        return $result->usage();
    }

    /**
     * Get the usage records for a metered product.
     */
    public function usageRecords(array $options = []): Collection
    {
        $all = Usage::all(array_merge([
            'subscriptionId[is]' => $this->subscription->chargebee_id,
            'itemPriceId[is]' => $this->chargebee_price,
        ], $options));

        $usageRecords = collect($all)->map(function ($entry) {
            return $entry->usage();
        });

        return $usageRecords;
    }

    /**
     * Update the underlying Chargebee subscription item information for the model.
     */
    public function updateChargebeeSubscriptionItem(array $itemOptions = [], array $subscriptionOptions = []): SubscriptionSubscriptionItem
    {
        $chargebeeSubscription = $this->subscription->updateChargebeeSubscriptionItem($this->chargebee_price, $itemOptions, $subscriptionOptions);
        $price = $itemOptions['itemPriceId'] ?? $this->chargebee_price;

        return collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', $price);
    }

    /**
     * Get the subscription item as a Chargebee SubscriptionSubscriptionItem object.
     */
    public function asChargebeeSubscriptionItem(): SubscriptionSubscriptionItem
    {
        $chargebeeSubscription = $this->subscription->asChargebeeSubscription();

        $subscriptionItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', $this->chargebee_price);

        if (!$subscriptionItem) {
            throw new ModelNotFoundException("Subscription item with price '{$this->chargebee_price}' not found in Chargebee.");
        }

        return $subscriptionItem;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return SubscriptionItemFactory::new();
    }
}
