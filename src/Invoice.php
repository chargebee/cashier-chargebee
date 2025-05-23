<?php

namespace Chargebee\Cashier;

use Carbon\Carbon;
use Chargebee\Cashier\Contracts\InvoiceRenderer;
use Chargebee\Cashier\Exceptions\InvalidInvoice;
use Chargebee\Resources\Invoice\Invoice as ChargeBeeInvoice;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\View\View as ViewView;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

class Invoice implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Chargebee invoice instance.
     *
     * @var ChargeBeeInvoice
     */
    protected $invoice;

    /**
     * The Chargebee invoice line items.
     *
     * @var array[]
     */
    protected $items;

    /**
     * @var string
     */
    protected $nextOffset;

    /**
     * Create a new invoice instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \Chargebee\Resources\Invoice\Invoice  $invoice
     * @param  array  $refreshData
     * @return void
     *
     * @throws \Chargebee\Cashier\Exceptions\InvalidInvoice
     */
    public function __construct($owner, ChargeBeeInvoice $invoice, $nextOffset = null)
    {
        if ($owner->chargebee_id !== $invoice->customer_id) {
            throw InvalidInvoice::invalidOwner($invoice, $owner);
        }

        $this->owner = $owner;
        $this->invoice = $invoice;
        $this->nextOffset = $nextOffset;
    }

    /**
     * Get a Carbon instance for the invoicing date.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return \Carbon\Carbon
     */
    public function date($timezone = null): Carbon
    {
        $carbon = Carbon::createFromTimestampUTC($this->invoice->date);

        return $timezone ? $carbon->setTimezone($timezone) : $carbon;
    }

    /**
     * Get a Carbon instance for the invoice's due date.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return \Carbon\Carbon|null
     */
    public function dueDate($timezone = null): ?Carbon
    {
        if ($this->invoice->due_date) {
            $carbon = Carbon::createFromTimestampUTC($this->invoice->due_date);

            return $timezone ? $carbon->setTimezone($timezone) : $carbon;
        }

        return null;
    }

    /**
     * Get the total amount that was paid (or will be paid).
     *
     * @return string
     */
    public function total(): string
    {
        return $this->formatAmount($this->rawTotal());
    }

    /**
     * Get the raw total amount that was paid (or will be paid).
     *
     * @return int
     */
    public function rawTotal(): mixed
    {
        return $this->invoice->total;
    }

    /**
     * Get the total of the invoice (before discounts).
     *
     * @return string
     */
    public function subtotal(): string
    {
        return $this->formatAmount($this->invoice->sub_total);
    }

    /**
     * Get the amount due for the invoice.
     *
     * @return string
     */
    public function amountDue(): string
    {
        return $this->formatAmount($this->rawAmountDue());
    }

    /**
     * Get the raw amount due for the invoice.
     *
     * @return int
     */
    public function rawAmountDue(): mixed
    {
        return $this->invoice->amount_due ?? 0;
    }

    /**
     * Determine if the invoice has one or more discounts applied.
     *
     * @return bool
     */
    public function hasDiscount(): bool
    {
        if (is_null($this->invoice->discounts)) {
            return false;
        }

        return count($this->invoice->discounts) > 0;
    }

    /**
     * Get all of the discount objects from the Chargebee invoice.
     *
     * @return \Chargebee\Cashier\Discount[]
     */
    public function discounts(): array
    {
        return Collection::make($this->invoice->discounts)
            ->mapInto(Discount::class)
            ->all();
    }

    /**
     * Calculate the amount for a given discount.
     *
     * @param  \Chargebee\Cashier\Discount  $discount
     * @return string|null
     */
    public function discountFor(Discount $discount): ?string
    {
        if (! is_null($discountAmount = $this->rawDiscountFor($discount))) {
            return $this->formatAmount($discountAmount);
        }

        return null;
    }

    /**
     * Calculate the raw amount for a given discount.
     *
     * @param  \Chargebee\Cashier\Discount  $discount
     * @return int|null
     */
    public function rawDiscountFor(Discount $discount): mixed
    {
        return optional(Collection::make($this->invoice->discounts)
            ->first(function ($discountAmount) use ($discount) {
                return $discountAmount->entity_id === $discount->entity_id;
            }))
            ->amount;
    }

    /**
     * Get the total discount amount.
     *
     * @return string
     */
    public function discount(): string
    {
        return $this->formatAmount($this->rawDiscount());
    }

    /**
     * Get the raw total discount amount.
     *
     * @return int
     */
    public function rawDiscount(): int
    {
        $total = 0;

        foreach ((array) $this->invoice->discounts as $discount) {
            $total += $discount->amount;
        }

        return (int) $total;
    }

    /**
     * Get the total tax amount.
     *
     * @return string
     */
    public function tax(): string
    {
        return $this->formatAmount($this->invoice->tax ?? 0);
    }

    /**
     * Determine if the invoice has tax applied.
     *
     * @return bool
     */
    public function hasTax(): bool
    {
        $lineItems = $this->invoiceItems() + $this->subscriptions();

        return Collection::make($lineItems)->contains(function (InvoiceLineItem $item) {
            return $item->hasTaxRates();
        });
    }

    /**
     * Get the taxes applied to the invoice.
     *
     * @return \Chargebee\Cashier\Tax[]
     */
    public function taxes(): array
    {
        return Collection::make($this->invoice->line_item_taxes)
            ->map(function ($lineItemTax) {
                return new Tax(
                    $lineItemTax->tax_amount,
                    $this->invoice->currency_code,
                    $lineItemTax->tax_rate
                );
            })
            ->all();
    }

    /**
     * Determine if the customer is not exempted from taxes.
     *
     * @return bool
     */
    public function isNotTaxExempt(): bool
    {
        return $this->owner()->isNotTaxExempt();
    }

    /**
     * Determine if the customer is exempted from taxes.
     *
     * @return bool
     */
    public function isTaxExempt(): bool
    {
        return $this->owner()->isTaxExempt();
    }

    /**
     * Determine if the invoice will charge the customer automatically.
     *
     * @return bool
     */
    public function chargesAutomatically(): bool
    {
        return $this->invoice->status->value === 'paid';
    }

    /**
     * Determine if the invoice will send an invoice to the customer.
     *
     * @return bool
     */
    public function sendsInvoice(): bool
    {
        return $this->invoice->status->value === 'pending' || $this->invoice->status->value === 'payment_due';
    }

    /**
     * Get all of the "invoice item" line items.
     *
     * @return \Chargebee\Cashier\InvoiceLineItem[]
     */
    public function invoiceItems(): array
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->subscription_id == null;
        })->all();
    }

    /**
     * Get all of the "subscription" line items.
     *
     * @return \Chargebee\Cashier\InvoiceLineItem[]
     */
    public function subscriptions(): array
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->subscription_id != null;
        })->all();
    }

    /**
     * Get all of the invoice items.
     *
     * @return \Chargebee\Cashier\InvoiceLineItem[]
     */
    public function invoiceLineItems(): array
    {
        if (! is_null($this->items)) {
            return $this->items;
        }

        $lineItems = $this->invoice->line_items;

        $items = Collection::make();

        foreach ($lineItems as $item) {
            $items->push(new InvoiceLineItem($this, $item));
        }

        return $this->items = $items->reverse()->all();
    }

    /**
     * Add an invoice item to this invoice.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $options
     * @return \Chargebee\Resources\Invoice\Invoice
     */
    public function tab($description, $amount, array $options = []): ChargeBeeInvoice
    {
        $chargebee = Cashier::chargebee();
        $item = $chargebee->invoice()->addCharge($this->invoice->id, array_merge($options, [
            'amount' => $amount,
            'description' => $description,
        ]));

        $this->invoice = $item->invoice;

        return $this->invoice;
    }

    /**
     * Add an invoice item for a specific Price ID to this invoice.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $options
     * @return \Chargebee\Resources\Invoice\Invoice
     */
    public function tabPrice($price, $quantity = 1, array $options = []): ChargeBeeInvoice
    {
        $chargebee = Cashier::chargebee();
        $item = $chargebee->invoice()->addChargeItem($this->invoice->id, array_merge($options, [
            'item_price' => [
                'item_price_id' => $price,
                'quantity' => $quantity,
            ],
        ]));

        $this->invoice = $item->invoice;

        return $this->invoice;
    }

    /**
     * Refresh the invoice.
     *
     * @return $this
     */
    public function refresh(): static
    {
        $chargebee = Cashier::chargebee();
        $this->invoice = $chargebee->invoice()->retrieve($this->invoice->id)->invoice;

        return $this;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount): string
    {
        return Cashier::formatAmount($amount, $this->invoice->currency_code);
    }

    /**
     * Pay the Chargebee invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function pay(array $options = []): static
    {
        $chargebee = Cashier::chargebee();
        if (Arr::get($options, 'off_session', true)) {
            $this->invoice = $chargebee->invoice()->recordPayment($this->invoice->id, array_merge([
                'transaction' => [
                    'payment_method' => 'other',
                ],
                $options,
            ]))->invoice;
        } else {
            $this->invoice = $chargebee->invoice()->collectPayment($this->invoice->id, $options)->invoice;
        }

        return $this;
    }

    /**
     * Void the Chargebee invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function void(array $options = []): static
    {
        $chargebee = Cashier::chargebee();
        $this->invoice = $chargebee->invoice()->voidInvoice($this->invoice->id, $options)->invoice;

        return $this;
    }

    /**
     * Delete the Chargebee invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function delete(array $options = []): static
    {
        $chargebee = Cashier::chargebee();
        $this->invoice = $chargebee->invoice()->delete($this->invoice->id, $options)->invoice;

        return $this;
    }

    /**
     * Determine if the invoice is open.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->invoice->status->value === 'payment_due';
    }

    /**
     * Determine if the invoice is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->invoice->status->value === 'pending';
    }

    /**
     * Determine if the invoice is paid.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->invoice->status->value === 'paid';
    }

    /**
     * Determine if the invoice is void.
     *
     * @return bool
     */
    public function isVoid(): bool
    {
        return $this->invoice->status->value === 'voided';
    }

    /**
     * Get the View instance for the invoice.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\View\View
     */
    public function view(array $data = []): ViewView
    {
        return View::make('cashier::invoice', array_merge($data, [
            'invoice' => $this,
            'owner' => $this->owner,
            'user' => $this->owner,
        ]));
    }

    /**
     * Capture the invoice as a PDF and return the raw bytes.
     *
     * @param  array  $data
     * @return string
     */
    public function pdf(array $data = []): string
    {
        $options = config('cashier.invoices.options', []);

        if ($paper = config('cashier.paper')) {
            $options['paper'] = $paper;
        }

        return app(InvoiceRenderer::class)->render($this, $data, $options);
    }

    /**
     * Create an invoice download response.
     *
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(array $data = []): Response
    {
        $filename = $data['product'] ?? Str::slug(config('app.name'));
        $filename .= '_'.$this->date()->month.'_'.$this->date()->year;

        return $this->downloadAs($filename, $data);
    }

    /**
     * Create an invoice download response with a specific filename.
     *
     * @param  string  $filename
     * @param  array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAs($filename, array $data = []): Response
    {
        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
            'X-Vapor-Base64-Encode' => 'True',
        ]);
    }

    /**
     * Get the Chargebee model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the Chargebee invoice instance.
     *
     * @return \Chargebee\Resources\Invoice\Invoice
     */
    public function asChargebeeInvoice(): ChargeBeeInvoice
    {
        return $this->invoice;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeeInvoice()->toArray();
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
     * Dynamically get values from the Chargebee object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        return $key == 'next_offset' ? $this->nextOffset : $this->invoice->{$key};
    }
}
