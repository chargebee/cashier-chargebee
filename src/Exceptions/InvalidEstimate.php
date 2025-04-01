<?php

namespace Chargebee\Cashier\Exceptions;

use Chargebee\Resources\InvoiceEstimate\InvoiceEstimate as ChargeBeeEstimate;
use Exception;

final class InvalidEstimate extends Exception
{
    /**
     * Create a new InvalidInvoice instance.
     *
     * @param  \Chargebee\Resources\InvoiceEstimate\InvoiceEstimate  $invoice
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(ChargeBeeEstimate $estimate, $owner)
    {
        return new static("The estimate `{$estimate->customer_id}` does not belong to this customer `$owner->chargebee_id`.");
    }
}
