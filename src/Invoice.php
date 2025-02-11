<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use JsonSerializable;
use Laravel\CashierChargebee\Contracts\InvoiceRenderer;
use Laravel\CashierChargebee\Exceptions\InvalidInvoice;
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
     * @param  \ChargeBee\ChargeBee\Models\Invoice  $invoice
     * @param  array  $refreshData
     * @return void
     *
     * @throws \Laravel\CashierChargebee\Exceptions\InvalidInvoice
     */
    public function __construct($owner, ChargeBeeInvoice $invoice, $nextOffset = null)
    {
        if ($owner->chargebee_id !== $invoice->customerId) {
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
    public function date($timezone = null)
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
    public function dueDate($timezone = null)
    {
        if ($this->invoice->dueDate) {
            $carbon = Carbon::createFromTimestampUTC($this->invoice->dueDate);

            return $timezone ? $carbon->setTimezone($timezone) : $carbon;
        }
    }

    /**
     * Get the total amount minus the starting balance that was paid (or will be paid).
     *
     * @return string
     */
    public function total()
    {
        return $this->formatAmount($this->rawTotal());
    }

    /**
     * Get the raw total amount minus the starting balance that was paid (or will be paid).
     *
     * @return int
     */
    public function rawTotal()
    {
        return $this->invoice->total;
    }

    /**
     * Get the total amount that was paid (or will be paid).
     *
     * @return string
     */
    public function realTotal()
    {
        return $this->formatAmount($this->rawRealTotal());
    }

    /**
     * Get the raw total amount that was paid (or will be paid).
     *
     * @return int
     */
    public function rawRealTotal()
    {
        return $this->invoice->total;
    }

    /**
     * Get the total of the invoice (before discounts).
     *
     * @return string
     */
    public function subtotal()
    {
        return $this->formatAmount($this->invoice->subTotal);
    }

    /**
     * Get the amount due for the invoice.
     *
     * @return string
     */
    public function amountDue()
    {
        return $this->formatAmount($this->rawAmountDue());
    }

    /**
     * Get the raw amount due for the invoice.
     *
     * @return int
     */
    public function rawAmountDue()
    {
        return $this->invoice->amountDue ?? 0;
    }

    /**
     * Determine if the account had a starting balance.
     *
     * @return bool
     */
    public function hasStartingBalance()
    {
        return $this->rawStartingBalance() < 0;
    }

    /**
     * Get the starting balance for the invoice.
     *
     * @return string
     */
    public function startingBalance()
    {
        return $this->formatAmount($this->rawStartingBalance());
    }

    /**
     * Get the raw starting balance for the invoice.
     *
     * @return int
     */
    public function rawStartingBalance()
    {
        return $this->invoice->starting_balance ?? 0;
    }

    /**
     * Determine if the account had an ending balance.
     *
     * @return bool
     */
    public function hasEndingBalance()
    {
        return ! is_null($this->invoice->ending_balance);
    }

    /**
     * Get the ending balance for the invoice.
     *
     * @return string
     */
    public function endingBalance()
    {
        return $this->formatAmount($this->rawEndingBalance());
    }

    /**
     * Get the raw ending balance for the invoice.
     *
     * @return int
     */
    public function rawEndingBalance()
    {
        return $this->invoice->ending_balance ?? 0;
    }

    /**
     * Determine if the invoice has balance applied.
     *
     * @return bool
     */
    public function hasAppliedBalance()
    {
        return $this->rawAppliedBalance() < 0;
    }

    /**
     * Get the applied balance for the invoice.
     *
     * @return string
     */
    public function appliedBalance()
    {
        return $this->formatAmount($this->rawAppliedBalance());
    }

    /**
     * Get the raw applied balance for the invoice.
     *
     * @return int
     */
    public function rawAppliedBalance()
    {
        return $this->rawStartingBalance() - $this->rawEndingBalance();
    }

    /**
     * Determine if the invoice has one or more discounts applied.
     *
     * @return bool
     */
    public function hasDiscount()
    {
        if (is_null($this->invoice->discounts)) {
            return false;
        }

        return count($this->invoice->discounts) > 0;
    }

    /**
     * Get all of the discount objects from the Chargebee invoice.
     *
     * @return \Laravel\CashierChargebee\Discount[]
     */
    public function discounts()
    {
        return Collection::make($this->invoice->discounts)
            ->mapInto(Discount::class)
            ->all();
    }

    /**
     * Calculate the amount for a given discount.
     *
     * @param  \Laravel\CashierChargebee\Discount  $discount
     * @return string|null
     */
    public function discountFor(Discount $discount)
    {
        if (! is_null($discountAmount = $this->rawDiscountFor($discount))) {
            return $this->formatAmount($discountAmount);
        }
    }

    /**
     * Calculate the raw amount for a given discount.
     *
     * @param  \Laravel\CashierChargebee\Discount  $discount
     * @return int|null
     */
    public function rawDiscountFor(Discount $discount)
    {
        return optional(Collection::make($this->invoice->discounts)
            ->first(function ($discountAmount) use ($discount) {
                return $discountAmount->entityId === $discount->entityId;
            }))
            ->amount;
    }

    /**
     * Get the total discount amount.
     *
     * @return string
     */
    public function discount()
    {
        return $this->formatAmount($this->rawDiscount());
    }

    /**
     * Get the raw total discount amount.
     *
     * @return int
     */
    public function rawDiscount()
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
    public function tax()
    {
        return $this->formatAmount($this->invoice->tax ?? 0);
    }

    /**
     * Determine if the invoice has tax applied.
     *
     * @return bool
     */
    public function hasTax()
    {
        $lineItems = $this->invoiceItems() + $this->subscriptions();

        return Collection::make($lineItems)->contains(function (InvoiceLineItem $item) {
            return $item->hasTaxRates();
        });
    }

    /**
     * Get the taxes applied to the invoice.
     *
     * @return \Laravel\CashierChargebee\Tax[]
     */
    public function taxes()
    {
        return Collection::make($this->invoice->lineItemTaxes)
            ->map(function ($lineItemTax) {
                return new Tax(
                    $lineItemTax->taxAmount,
                    $this->invoice->currencyCode,
                    $lineItemTax->taxRate
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
    public function chargesAutomatically()
    {
        return $this->invoice->autoCollection === 'on';
    }

    /**
     * Determine if the invoice will send an invoice to the customer.
     *
     * @return bool
     */
    public function sendsInvoice()
    {
        return $this->invoice->autoCollection === 'off';
    }

    /**
     * Get all of the "invoice item" line items.
     *
     * @return \Laravel\CashierChargebee\InvoiceLineItem[]
     */
    public function invoiceItems()
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->subscriptionId == null;
        })->all();
    }

    /**
     * Get all of the "subscription" line items.
     *
     * @return \Laravel\CashierChargebee\InvoiceLineItem[]
     */
    public function subscriptions()
    {
        return Collection::make($this->invoiceLineItems())->filter(function (InvoiceLineItem $item) {
            return $item->subscriptionId != null;
        })->all();
    }

    /**
     * Get all of the invoice items.
     *
     * @return \Laravel\CashierChargebee\InvoiceLineItem[]
     */
    public function invoiceLineItems()
    {
        if (! is_null($this->items)) {
            return $this->items;
        }

        $lineItems = $this->invoice->lineItems;

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
     * @return \ChargeBee\ChargeBee\Models\Invoice
     */
    public function tab($description, $amount, array $options = [])
    {
        $item = ChargeBeeInvoice::addCharge($this->invoice->id, array_merge($options, [
            'amount' => $amount,
            'description' => $description,
        ]));

        $this->invoice = $item->invoice();

        return $this->invoice;
    }

    /**
     * Add an invoice item for a specific Price ID to this invoice.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $options
     * @return \ChargeBee\ChargeBee\Models\Invoice
     */
    public function tabPrice($price, $quantity = 1, array $options = [])
    {
        $item = ChargeBeeInvoice::addChargeItem($this->invoice->id, array_merge($options, [
            'itemPrice' => [
                'itemPriceId' => $price,
                'quantity' => $quantity,
            ],
        ]));

        $this->invoice = $item->invoice();

        return $this->invoice;
    }

    /**
     * Refresh the invoice.
     *
     * @return $this
     */
    public function refresh()
    {
        $this->invoice = ChargeBeeInvoice::retrieve($this->invoice->id)->invoice();

        return $this;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->invoice->currencyCode);
    }

    /**
     * Return the Tax Ids of the account.
     *
     * @return array
     */
    public function accountTaxIds()
    {
        return $this->invoice->account_tax_ids ?? [];
    }

    /**
     * Return the Tax Ids of the customer.
     *
     * @return array
     */
    public function customerTaxIds()
    {
        return $this->invoice->customer_tax_ids ?? [];
    }

    /**
     * Pay the Chargebee invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function pay(array $options = [])
    {
        if (Arr::get($options, 'off_session', true)) {
            $this->invoice = ChargeBeeInvoice::recordPayment($this->invoice->id, array_merge([
                'transaction' => [
                    'paymentMethod' => 'other',
                ],
                $options,
            ]))->invoice();
        } else {
            $this->invoice = ChargeBeeInvoice::collectPayment($this->invoice->id, $options)->invoice();
        }

        return $this;
    }

    /**
     * Void the Chargebee invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function void(array $options = [])
    {
        $this->invoice = ChargeBeeInvoice::voidInvoice($this->invoice->id, $options)->invoice();

        return $this;
    }

    /**
     * Delete the Chargebee invoice.
     *
     * @param  array  $options
     * @return $this
     */
    public function delete(array $options = [])
    {
        $this->invoice = ChargeBeeInvoice::delete($this->invoice->id, $options)->invoice();

        return $this;
    }

    /**
     * Determine if the invoice is open.
     *
     * @return bool
     */
    public function isOpen()
    {
        return $this->invoice->status === 'pending';
    }

    /**
     * Determine if the invoice is a draft.
     *
     * @return bool
     */
    public function isDraft()
    {
        return $this->invoice->status === '';
    }

    /**
     * Determine if the invoice is paid.
     *
     * @return bool
     */
    public function isPaid()
    {
        return $this->invoice->status === 'paid';
    }

    /**
     * Determine if the invoice is void.
     *
     * @return bool
     */
    public function isVoid()
    {
        return $this->invoice->status === 'voided';
    }

    /**
     * Get the View instance for the invoice.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\View\View
     */
    public function view(array $data = [])
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
    public function pdf(array $data = [])
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
    public function download(array $data = [])
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
    public function downloadAs($filename, array $data = [])
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
     * @return \ChargeBee\ChargeBee\Models\Invoice
     */
    public function asChargebeeInvoice()
    {
        return $this->invoice;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asChargebeeInvoice()->getValues();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
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
    public function __get($key)
    {
        return $key == 'next_offset' ? $this->nextOffset : $this->invoice->{$key};
    }
}
