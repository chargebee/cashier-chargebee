<?php

namespace Chargebee\Cashier\Exceptions;

use Chargebee\Resources\PromotionalCredit\PromotionalCredit;
use Exception;

final class InvalidCustomerBalanceTransaction extends Exception
{
    /**
     * Create a new CustomerBalanceTransaction instance.
     *
     * @param  \Chargebee\Resources\PromotionalCredit\PromotionalCredit  $transaction
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(PromotionalCredit $transaction, $owner)
    {
        return new static("The transaction `{$transaction->id}` does not belong to customer `$owner->chargebee_id`.");
    }
}
