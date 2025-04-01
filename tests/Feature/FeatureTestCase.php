<?php

namespace Chargebee\Cashier\Tests\Feature;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Tests\Fixtures\User;
use Chargebee\Cashier\Tests\TestCase;
use Chargebee\Resources\ItemPrice\ItemPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase, WithLaravelMigrations;

    protected function setUp(): void
    {
        // if (! getenv('CHARGEBEE_SITE') || ! getenv('CHARGEBEE_API_KEY')) {
        //     $this->markTestSkipped('Chargebee site or API key not set.');
        // }

        parent::setUp();

        Cashier::configureEnvironment();
    }

    protected function createCustomer($description = 'testuser', array $options = []): User
    {
        return User::create(array_merge([
            'email' => "{$description}@cashier-chargebee.com",
            'name' => 'Test User',
            'password' => '$2y$10$kJd93qWbF8VX4EPlRxGvBOipmKz6W5Q1yTUapXaR3YUgT76Z.jU.e',
        ], $options));
    }

    protected function createPrice($price, $amount): ItemPrice
    {
        $ts = now()->timestamp;
        $id = strtolower(str_replace(' ', '_', $price)).'-'.$ts;
        $chargebee = Cashier::chargebee();
        $itemFamily = $chargebee->itemFamily()->create([
            'id' => $id,
            'name' => "$price-$ts",
        ]);

        $item = $chargebee->item()->create([
            'id' => $id,
            'name' => "$price-$ts",
            'type' => 'charge',
            'item_family_id' => $itemFamily->item_family->id,
        ]);

        $itemPrice = $chargebee->itemPrice()->create([
            'id' => $id,
            'name' => "$price-$ts",
            'external_name' => $price,
            'description' => $price,
            'price' => $amount,
            'pricing_model' => 'per_unit',
            'item_id' => $item->item->id,
            'item_family_id' => $itemFamily->item_family->id,
            'currency_code' => config('cashier.currency'),
        ]);

        return $itemPrice->item_price;
    }

    protected function createSubscriptionPrice($price, $amount)
    {
        $ts = now()->timestamp;
        $chargebee = Cashier::chargebee();
        $itemFamily = $chargebee->itemFamily()->create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
        ]);

        $item = $chargebee->item()->create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'type' => 'plan',
            'item_family_id' => $itemFamily->item_family->id,
        ]);

        $itemPrice = $chargebee->itemPrice()->create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'price' => $amount,
            'pricing_model' => 'per_unit',
            'item_id' => $item->item->id,
            'item_family_id' => $itemFamily->item_family->id,
            'currency_code' => config('cashier.currency'),
            'period' => 1,
            'period_unit' => 'year',
        ]);

        return $itemPrice->item_price;
    }
}
