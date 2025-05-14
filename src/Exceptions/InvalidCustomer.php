<?php

namespace Chargebee\Cashier\Exceptions;

use Exception;

final class InvalidCustomer extends Exception
{
    /**
     * Create a new InvalidCustomer instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function notYetCreated($owner)
    {
        return new self(class_basename($owner).' is not a Chargebeee customer yet. See the createAsChargebeeCustomer method.');
    }
}
