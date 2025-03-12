<?php

namespace Chargebee\CashierChargebee\Exceptions;

use ChargeBee\ChargeBee\Models\InvoiceEstimate as ChargeBeeEstimate;
use Exception;

final class InvalidEstimate extends Exception
{
    /**
     * Create a new InvalidInvoice instance.
     *
     * @param  \ChargeBee\ChargeBee\Models\Invoice  $invoice
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(ChargeBeeEstimate $estimate, $owner)
    {
        return new static("The estimate `{$estimate->id}` does not belong to this customer `$owner->chargebee_id`.");
    }
}
