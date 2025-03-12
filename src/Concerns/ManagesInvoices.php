<?php

namespace Chargebee\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Estimate as ChargeBeeEstimate;
use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator as IlluminatePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Chargebee\CashierChargebee\Estimate;
use Chargebee\CashierChargebee\Exceptions\InvalidInvoice;
use Chargebee\CashierChargebee\Invoice;
use Chargebee\CashierChargebee\InvoiceBuilder;
use Chargebee\CashierChargebee\Paginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoices
{
    public function newInvoice()
    {
        $this->assertCustomerExists();

        return new InvoiceBuilder($this);
    }

    /**
     * Invoice the customer for the given Price ID and generate an invoice immediately.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Chargebee\CashierChargebee\Invoice
     *
     * @throws \Chargebee\CashierChargebee\Exceptions\IncompletePayment
     */
    public function invoicePrice($price, $quantity = 1, array $tabOptions = [], array $invoiceOptions = [])
    {
        return $this->newInvoice()
            ->tabPrice($price, $quantity, $tabOptions)
            ->invoice($invoiceOptions);
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $tabOptions
     * @param  array  $invoiceOptions
     * @return \Chargebee\CashierChargebee\Invoice
     *
     * @throws \Chargebee\CashierChargebee\Exceptions\IncompletePayment
     */
    public function invoiceFor($description, $amount, array $tabOptions = [], array $invoiceOptions = [])
    {
        return $this->newInvoice()
            ->tabFor($description, $amount, $tabOptions)
            ->invoice($invoiceOptions);
    }

    /**
     * Get the customer's upcoming invoice.
     *
     * @param  array  $options
     * @return \Chargebee\CashierChargebee\Estimate|null
     */
    public function upcomingInvoice(array $options = []): Estimate|null
    {
        if (! $this->hasChargebeeId()) {
            return null;
        }

        try {
            if (Arr::has($options, 'subscriptionId')) {
                $chargebeeEstimate = ChargeBeeEstimate::advanceInvoiceEstimate(
                    $options['subscriptionId'],
                    $options
                );

                return new Estimate($this, $chargebeeEstimate->estimate()->invoiceEstimate);
            } else {
                $chargebeeEstimate = ChargeBeeEstimate::upcomingInvoicesEstimate($this->chargebeeId());

                return new Estimate($this, $chargebeeEstimate->estimate()->invoiceEstimates[0]);
            }
        } catch (InvalidRequestException $exception) {
            return null;
        }
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Chargebee\CashierChargebee\Invoice|null
     */
    public function findInvoice($id)
    {
        $chargebeeInvoice = null;

        try {
            $chargebeeInvoice = ChargeBeeInvoice::retrieve($id)->invoice();
        } catch (InvalidRequestException $exception) {
            //
        }

        return $chargebeeInvoice ? new Invoice($this, $chargebeeInvoice) : null;
    }

    /**
     * Find an invoice or throw a 404 or 403 error.
     *
     * @param  string  $id
     * @return \Chargebee\CashierChargebee\Invoice
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function findInvoiceOrFail($id)
    {
        try {
            $invoice = $this->findInvoice($id);
        } catch (InvalidInvoice $exception) {
            throw new AccessDeniedHttpException;
        }

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @param  string  $filename
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data = [], $filename = null)
    {
        $invoice = $this->findInvoiceOrFail($id);

        return $filename ? $invoice->downloadAs($filename, $data) : $invoice->download($data);
    }

    /**
     * Get a collection of the customer's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Chargebee\CashierChargebee\Invoice[]
     */
    public function invoices($includePending = false, $parameters = [])
    {
        if (! $this->hasChargebeeId()) {
            return new Collection();
        }

        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $chargebeeInvoices = ChargeBeeInvoice::all(
            ['customerId[is]' => $this->chargebeeId()] + $parameters
        );

        if (! is_null($chargebeeInvoices)) {
            foreach ($chargebeeInvoices as $chargebeeInvoice) {
                $invoice = $chargebeeInvoice->invoice();
                if ($invoice->status == 'paid' || $includePending) {
                    $invoices[] = new Invoice(
                        $this,
                        $invoice,
                        $chargebeeInvoices->nextOffset()
                    );
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Get an array of the customer's invoices, including pending invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Chargebee\CashierChargebee\Invoice[]
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Get a cursor paginator for the customer's invoices.
     *
     * @param  int|null  $perPage
     * @param  array  $parameters
     * @param  string  $cursorName
     * @param  \Illuminate\Pagination\Cursor|string|null  $cursor
     * @return \Chargebee\CashierChargebee\Paginator
     */
    public function cursorPaginateInvoices($perPage = 24, array $parameters = [], $cursorName = 'cursor', $cursor = null)
    {
        if (! $cursor instanceof Cursor) {
            $cursor = is_string($cursor)
                ? Cursor::fromEncoded($cursor)
                : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
        }

        if (! is_null($cursor)) {
            $parameters['offset'] = $cursor->parameter('next_offset');
        }

        $invoices = $this->invoices(true, array_merge($parameters, ['limit' => $perPage]));

        $hasMore = count($invoices) ? $invoices[0]->next_offset != null : false;

        return new Paginator(
            $invoices,
            $perPage,
            $hasMore,
            $cursor,
            array_merge([
                'path' => IlluminatePaginator::resolveCurrentPath(),
                'cursorName' => $cursorName,
                'parameters' => ['next_offset'],
            ])
        );
    }
}
