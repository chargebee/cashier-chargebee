<?php

namespace Chargebee\Cashier\Tests\Fixtures;

use Chargebee\Cashier\Billable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Model;
use Illuminate\Notifications\Notifiable;

class User extends Model
{
    use Billable, HasFactory, Notifiable;

    protected $guarded = [];

    /**
     * Get the default billing address.
     */
    public function chargebeeBillingAddress(): array
    {
        return [
            'firstName' => 'Test',
            'lastName' => 'User',
            'line1' => 'PO Box 9999',
            'city' => 'Walnut',
            'state' => 'California',
            'zip' => '91789',
            'country' => 'US',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
