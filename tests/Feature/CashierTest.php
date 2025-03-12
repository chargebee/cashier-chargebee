<?php

namespace Chargebee\Cashier\Tests\Feature;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Tests\Fixtures\UserSoftDeletable;
use ChargeBee\ChargeBee\Environment;

class CashierTest extends FeatureTestCase
{
    public function test_it_can_find_billable_customer_by_chargebee_id(): void
    {
        $this->createCustomer('test', ['chargebee_id' => 'test_chargebee_id']);

        $foundCustomer = Cashier::findBillable('test_chargebee_id');

        $this->assertNotNull($foundCustomer);
        $this->assertSame('test_chargebee_id', $foundCustomer->chargebee_id);

        Cashier::useCustomerModel(UserSoftDeletable::class);
        $foundCustomer = Cashier::findBillable('test_chargebee_id');

        $this->assertNotNull($foundCustomer);
        $this->assertSame('test_chargebee_id', $foundCustomer->chargebee_id);
    }

    public function test_it_can_configure_chargebee_environment(): void
    {
        $config = Environment::defaultEnv();

        $this->assertSame(getenv('CHARGEBEE_SITE'), $config->getSite());
        $this->assertSame(getenv('CHARGEBEE_API_KEY'), $config->getApiKey());
    }
}
