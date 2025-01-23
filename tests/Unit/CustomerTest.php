<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use Laravel\CashierChargebee\Exceptions\CustomerAlreadyCreated;
use Laravel\CashierChargebee\Exceptions\CustomerNotFound;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Laravel\CashierChargebee\Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_chargebee_customer_cannot_be_created_when_chargebee_id_is_already_set(): void
    {
        $user = new User();
        $user->chargebee_id = 'foo';

        $this->expectException(CustomerAlreadyCreated::class);

        $user->createAsChargebeeCustomer();
    }

    public function test_customer_not_found_is_throwed_with_no_chargebee_id(): void
    {
        $user = new User();

        $this->expectException(CustomerNotFound::class);

        $user->asChargebeeCustomer();
    }

    public function test_customer_not_found_is_throwed_with_invalid_chargebee_id(): void
    {
        $user = new User();
        $user->chargebee_id = 'foo';

        $this->expectException(CustomerNotFound::class);

        $user->asChargebeeCustomer();
    }

    public function test_update_chargebee_customer_with_no_chargebee_id(): void
    {
        $user = new User();

        $this->expectException(CustomerNotFound::class);

        $user->updateChargebeeCustomer();
    }

    public function test_update_chargebee_customer_with_invalid_chargebee_id(): void
    {
        $user = new User();
        $user->chargebee_id = 'foo';

        $this->expectException(CustomerNotFound::class);

        $user->updateChargebeeCustomer();
    }

    public function test_preferred_currency(): void
    {
        $user = new User();

        $this->assertSame($user->preferredCurrency(), config('cashier.currency'));
    }

    public function test_format_amount(): void
    {
        config(['cashier.currency' => 'EUR']);

        $user = new User();

        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'formatAmount'
        );

        $result = $reflectedMethod->invoke($user, 1000);

        $this->assertStringContainsString('10.00', $result);
        $this->assertStringContainsString('€', $result);
    }
}
