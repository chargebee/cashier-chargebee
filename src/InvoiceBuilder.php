<?php

namespace Chargebee\Cashier;

use Illuminate\Support\Arr;

class InvoiceBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var \Chargebee\Cashier\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    protected $charges;

    protected $itemPrices;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $type
     * @param  string|string[]|array[]  $prices
     * @return void
     */
    public function __construct($owner)
    {
        $this->owner = $owner;
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $tabOptions
     * @return \Chargebee\Cashier\InvoiceBuilder
     */
    public function tabFor($description, $amount, array $tabOptions = []): static
    {
        $this->charges[] = array_merge([
            'amount' => $amount,
            'description' => $description,
        ], $tabOptions);

        return $this;
    }

    /**
     * Invoice the customer for the given Price ID and generate an invoice immediately.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $tabOptions
     * @return \Chargebee\Cashier\InvoiceBuilder
     */
    public function tabPrice($price, $quantity = 1, array $tabOptions = []): static
    {
        $this->itemPrices[] = array_merge([
            'item_price_id' => $price,
            'quantity' => $quantity,
        ], $tabOptions);

        return $this;
    }

    /**
     * Invoice the customer outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Chargebee\Cashier\Invoice
     */
    public function invoice(?array $options = []): Invoice
    {
        $data = array_filter(array_merge([
            'customer_id' => $this->owner->chargebeeId(),
            'currency_code' => $this->owner->preferredCurrency(),
            'charges' => $this->charges,
            'item_prices' => $this->itemPrices,
        ], $options));

        if (Arr::has($data, 'subscription_id')) {
            Arr::forget($data, ['customer_id', 'currency_code']);
        }
        $chargebee = Cashier::chargebee();
        $response = $chargebee->invoice()->createForChargeItemsAndCharges($data);

        return new Invoice($this->owner, $response->invoice);
    }
}
