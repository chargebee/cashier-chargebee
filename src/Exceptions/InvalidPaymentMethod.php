<?php

namespace Laravel\CashierChargebee\Exceptions;

use ChargeBee\ChargeBee\Models\PaymentSource;
use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidPaymentMethod extends Exception
{
    /**
     * Create a new InvalidPaymentMethod instance.
     */
    public static function invalidOwner(PaymentSource $paymentMethod, Model $owner): static
    {
        return new static(
            "The payment method `{$paymentMethod->id}`'s customer `{$paymentMethod->customerId}` does not belong to this customer `$owner->chargebee_id`."
        );
    }
}
