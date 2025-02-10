<?php

namespace Laravel\CashierChargebee\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use WithWorkbench;

    protected function getEnvironmentSetUp($app): void
    {
        $apiKey = config('cashier.api_key');

        if ($apiKey && ! Str::startsWith($apiKey, 'test')) {
            throw new InvalidArgumentException('Tests may not be run with a production Chargebee key.');
        }

        Cashier::useCustomerModel(User::class);
    }

    protected function defineEnvironment($app)
    {
        tap($app['config'], function (Repository $config) {
            $config->set('cashier.currency', env('CASHIER_CURRENCY', 'USD'));
        });
    }
}
