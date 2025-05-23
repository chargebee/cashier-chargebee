<?php

namespace Chargebee\Cashier\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

final class CustomerAlreadyCreated extends Exception
{
    /**
     * Create a new CustomerAlreadyCreated instance.
     */
    public static function exists(Model $owner): static
    {
        return new self(class_basename($owner)." is already a Chargebee customer with ID {$owner->chargebee_id}.");
    }
}
