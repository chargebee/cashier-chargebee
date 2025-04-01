<?php

namespace Chargebee\Cashier;

use Chargebee\Cashier\Concerns\Prorates;
use Chargebee\Cashier\Database\Factories\SubscriptionItemFactory;
use Chargebee\Resources\ItemPrice\ItemPrice;
use Chargebee\Resources\Subscription\SubscriptionItem as ChargebeeSubscriptionItem;
use Chargebee\Resources\Usage\Usage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
        $chargebee = Cashier::chargebee();
        $item = ['item_price_id' => $price];
        if ($this->quantity) {
            $item['quantity'] = $this->quantity;
        }

        $itemOptions = array_merge($item, $itemOptions);

        if (! is_null($this->prorateBehavior())) {
            $subscriptionOptions['prorate'] = $this->prorateBehavior();
        }

        $chargebeeSubscriptionItem = $this->updateChargebeeSubscriptionItem($itemOptions, $subscriptionOptions);

        $this->fill([
            'chargebee_product' => $chargebee->itemPrice()->retrieve($price)->item_price->item_id,
            'chargebee_price' => $chargebeeSubscriptionItem->item_price_id,
            'quantity' => $chargebeeSubscriptionItem->quantity,
        ])->save();

        $chargebeeSubscription = $this->subscription->asChargebeeSubscription();
        $this->subscription->refreshSubscriptionAttributes($chargebeeSubscription);

        return $this;
    }

    /**
     * Swap the subscription item to a new Chargebee price, and invoice immediately.
     */
    public function swapAndInvoice(string $price, array $itemOptions = [], array $subscriptionOptions = []): static
    {
        $subscriptionOptions['invoice_immediately'] = true;

        return $this->swap($price, $itemOptions, $subscriptionOptions);
    }

    /**
     * Report usage for a metered product.
     */
    public function reportUsage(int $quantity = 1, DateTimeInterface|int|null $timestamp = null): Usage
    {
        $chargebee = Cashier::chargebee();
        $timestamp = $timestamp instanceof DateTimeInterface ? $timestamp->getTimestamp() : $timestamp;

        $result = $chargebee->usage()->create($this->subscription->chargebee_id, [
            'item_price_id' => $this->chargebee_price,
            'quantity' => $quantity,
            'usage_date' => $timestamp ?? time(),
        ]);

        return $result->usage;
    }

    /**
     * Get the usage records for a metered product.
     */
    public function usageRecords(array $options = []): Collection
    {
        $chargebee = Cashier::chargebee();
        $all = $chargebee->usage()->all(array_merge([
            'subscription_id[is]' => $this->subscription->chargebee_id,
            'item_price_id[is]' => $this->chargebee_price,
        ], $options));

        return collect($all->list)->map(function ($entry) {
            return $entry->usage;
        });
    }

    /**
     * Update the underlying Chargebee subscription item information for the model.
     */
    public function updateChargebeeSubscriptionItem(array $itemOptions = [], array $subscriptionOptions = []): ChargebeeSubscriptionItem
    {
        $chargebeeSubscription = $this->subscription->updateChargebeeSubscriptionItem($this->chargebee_price, $itemOptions, $subscriptionOptions);
        $price = $itemOptions['item_price_id'] ?? $this->chargebee_price;

        return collect($chargebeeSubscription->subscription_items)->firstWhere('item_price_id', $price);
    }

    /**
     * Get the subscription item as a Chargebee SubscriptionSubscriptionItem object.
     *
     * @throws ModelNotFoundException
     */
    public function asChargebeeSubscriptionItem(): ChargebeeSubscriptionItem
    {
        $chargebeeSubscription = $this->subscription->asChargebeeSubscription();

        $subscriptionItem = collect($chargebeeSubscription->subscription_items)->firstWhere('item_price_id', $this->chargebee_price);

        if (! $subscriptionItem) {
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
