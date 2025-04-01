<?php

namespace Chargebee\Cashier\Exceptions;

use Chargebee\Resources\PaymentSource\PaymentSource;
use Exception;
use Illuminate\Database\Eloquent\Model;

final class InvalidPaymentMethod extends Exception
{
    /**
     * Create a new InvalidPaymentMethod instance.
     */
    public static function invalidOwner(PaymentSource $paymentMethod, Model $owner): static
    {
        return new static(
            "The payment method `{$paymentMethod->id}`'s customer `{$paymentMethod->customer_id}` does not belong to this customer `$owner->chargebee_id`."
        );
    }
}
