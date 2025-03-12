<?php

namespace Chargebee\CashierChargebee\Exceptions;

use Exception;

final class PaymentNotFound extends Exception
{
    /**
     * Create a new PaymentNotFound instance.
     */
    public static function notFound(string $paymentId): static
    {
        return new static("Payment with ID {$paymentId} was not found.");
    }
}
