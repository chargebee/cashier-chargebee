<?php

namespace Chargebee\Cashier\Contracts;

use Chargebee\Cashier\Invoice;

interface InvoiceRenderer
{
    /**
     * Render the invoice as a PDF and return the raw bytes.
     *
     * @param  \Chargebee\Cashier\Invoice  $invoice
     * @param  array  $data
     * @param  array  $options
     * @return string
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string;
}
