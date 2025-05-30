<?php

namespace Chargebee\Cashier\Exceptions;

use Chargebee\Cashier\Subscription;
use Exception;

final class SubscriptionUpdateFailure extends Exception
{
    /**
     * Create a new SubscriptionUpdateFailure instance.
     */
    public static function duplicatePrice(Subscription $subscription, string $price): static
    {
        return new self(
            "The price \"$price\" is already attached to subscription \"{$subscription->chargebee_id}\"."
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure instance.
     */
    public static function cannotDeleteLastPrice(Subscription $subscription): static
    {
        return new static(
            "The price on subscription \"{$subscription->chargebee_id}\" cannot be removed because it is the last one."
        );
    }
}
