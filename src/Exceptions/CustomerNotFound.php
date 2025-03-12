<?php

namespace Chargebee\Cashier\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

final class CustomerNotFound extends Exception
{
    /**
     * Create a new CustomerNotFound instance.
     */
    public static function notFound(Model $owner): static
    {
        return new static(class_basename($owner).' is not a Chargebee customer yet or chargebee_id is invalid. See the createAsChargebeeCustomer method.');
    }
}
