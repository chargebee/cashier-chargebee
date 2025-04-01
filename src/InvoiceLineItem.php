<?php

namespace Chargebee\Cashier;

use Carbon\Carbon;
use Chargebee\Resources\Invoice\LineItem as ChargeBeeInvoiceLineItem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class InvoiceLineItem implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Cashier Invoice instance.
     *
     * @var \Chargebee\Cashier\Invoice
     */
    protected $invoice;

    /**
     * The Chargebee invoice line item instance.
     *
     * @var \Chargebee\Resources\Invoice\LineItem
     */
    protected $item;

    /**
     * Create a new invoice line item instance.
     *
     * @param  \Chargebee\Cashier\Invoice  $invoice
     * @param  \Chargebee\Resources\Invoice\LineItem  $item
     * @return void
     */
    public function __construct(Invoice $invoice, ChargeBeeInvoiceLineItem $item)
    {
        $this->invoice = $invoice;
        $this->item = $item;
    }

    /**
     * Get the total for the invoice line item.
     *
     * @return string
     */
    public function total(): string
    {
        return $this->formatAmount($this->item->amount);
    }

    /**
     * Get the unit amount excluding tax for the invoice line item.
     *
     * @return string
     */
    public function unitAmountExcludingTax(): string
    {
        return $this->formatAmount($this->item->unit_amount - $this->item->tax_amount);
    }

    /**
     * Get the total percentage of the default inclusive tax for the invoice line item.
     *
     * @return float
     */
    public function inclusiveTaxPercentage(): mixed
    {
        return $this->invoice->price_type->value == 'tax_inclusive' ? $this->item->tax_rate : 0;
    }

    /**
     * Get the total percentage of the default exclusive tax for the invoice line item.
     *
     * @return float
     */
    public function exclusiveTaxPercentage(): mixed
    {
        return $this->invoice->price_type->value == 'tax_exclusive' ? $this->item->tax_rate : 0;
    }

    /**
     * Determine if the invoice line item has tax rates.
     *
     * @return bool
     */
    public function hasTaxRates(): bool
    {
        return ! empty($this->item->tax_rate);
    }

    /**
     * Get a human readable date for the start date.
     *
     * @return string|null
     */
    public function startDate(): string|null
    {
        if ($this->hasPeriod()) {
            return $this->startDateAsCarbon()->toFormattedDateString();
        }

        return null;
    }

    /**
     * Get a human readable date for the end date.
     *
     * @return string|null
     */
    public function endDate(): string|null
    {
        if ($this->hasPeriod()) {
            return $this->endDateAsCarbon()->toFormattedDateString();
        }

        return null;
    }

    /**
     * Get a Carbon instance for the start date.
     *
     * @return \Carbon\Carbon|null
     */
    public function startDateAsCarbon(): Carbon|null
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->date_from);
        }

        return null;
    }

    /**
     * Get a Carbon instance for the end date.
     *
     * @return \Carbon\Carbon|null
     */
    public function endDateAsCarbon(): Carbon|null
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->date_to);
        }

        return null;
    }

    /**
     * Determine if the invoice line item has a defined period.
     *
     * @return bool
     */
    public function hasPeriod(): bool
    {
        return ! is_null($this->item->date_from) && ! is_null($this->item->date_to);
    }

    /**
     * Determine if the invoice line item has a period with the same start and end date.
     *
     * @return bool
     */
    public function periodStartAndEndAreEqual(): bool
    {
        return $this->hasPeriod() ? $this->item->date_from === $this->item->date_to : false;
    }

    /**
     * Determine if the invoice line item is for a subscription.
     *
     * @return bool
     */
    public function isSubscription(): bool
    {
        return $this->item->subscription_id != null;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount): string
    {
        return Cashier::formatAmount($amount, $this->invoice()->currency_code);
    }

    /**
     * Get the Chargebee model instance.
     *
     * @return \Chargebee\Cashier\Invoice
     */
    public function invoice(): Invoice
    {
        return $this->invoice;
    }

    /**
     * Get the underlying Chargebee invoice line item.
     *
     * @return \ChargeBee\ChargeBee\Models\InvoiceLineItem
     */
    public function asChargebeeInvoiceLineItem(): ChargeBeeInvoiceLineItem
    {
        return $this->item;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeeInvoiceLineItem()->getValues();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): bool|string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically access the Chargebee invoice line item instance.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        return $this->item->{$key};
    }
}
