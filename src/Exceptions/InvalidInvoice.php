<?php

namespace Chargebee\CashierChargebee\Exceptions;

use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;
use Exception;

final class InvalidInvoice extends Exception
{
    /**
     * Create a new InvalidInvoice instance.
     *
     * @param  \ChargeBee\ChargeBee\Models\Invoice  $invoice
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(ChargeBeeInvoice $invoice, $owner)
    {
        return new static("The invoice `{$invoice->id}` does not belong to this customer `$owner->chargebee_id`.");
    }
}
