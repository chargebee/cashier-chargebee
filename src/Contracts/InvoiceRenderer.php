<?php

namespace Chargebee\CashierChargebee\Contracts;

use Chargebee\CashierChargebee\Invoice;

interface InvoiceRenderer
{
    /**
     * Render the invoice as a PDF and return the raw bytes.
     *
     * @param  \Chargebee\CashierChargebee\Invoice  $invoice
     * @param  array  $data
     * @param  array  $options
     * @return string
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string;
}
