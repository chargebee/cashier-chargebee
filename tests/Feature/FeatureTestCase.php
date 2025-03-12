<?php

namespace Chargebee\CashierChargebee\Tests\Feature;

use Chargebee\CashierChargebee\Cashier;
use Chargebee\CashierChargebee\Tests\Fixtures\User;
use Chargebee\CashierChargebee\Tests\TestCase;
use ChargeBee\ChargeBee\Models\Item;
use ChargeBee\ChargeBee\Models\ItemFamily;
use ChargeBee\ChargeBee\Models\ItemPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase, WithLaravelMigrations;

    protected function setUp(): void
    {
        if (! getenv('CHARGEBEE_SITE') || ! getenv('CHARGEBEE_API_KEY')) {
            $this->markTestSkipped('Chargebee site or API key not set.');
        }

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
        $itemFamily = ItemFamily::create([
            'id' => $id,
            'name' => "$price-$ts",
        ]);

        $item = Item::create([
            'id' => $id,
            'name' => "$price-$ts",
            'type' => 'charge',
            'itemFamilyId' => $itemFamily->itemFamily()->id,
        ]);

        $itemPrice = ItemPrice::create([
            'id' => $id,
            'name' => "$price-$ts",
            'externalName' => $price,
            'description' => $price,
            'price' => $amount,
            'pricingModel' => 'per_unit',
            'itemId' => $item->item()->id,
            'itemFamilyId' => $itemFamily->itemFamily()->id,
            'currencyCode' => config('cashier.currency'),
        ]);

        return $itemPrice->itemPrice();
    }

    protected function createSubscriptionPrice($price, $amount)
    {
        $ts = now()->timestamp;

        $itemFamily = ItemFamily::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
        ]);

        $item = Item::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'type' => 'plan',
            'itemFamilyId' => $itemFamily->itemFamily()->id,
        ]);

        $itemPrice = ItemPrice::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'price' => $amount,
            'pricingModel' => 'per_unit',
            'itemId' => $item->item()->id,
            'itemFamilyId' => $itemFamily->itemFamily()->id,
            'currencyCode' => config('cashier.currency'),
            'period' => 1,
            'periodUnit' => 'year',
        ]);

        return $itemPrice->itemPrice();
    }
}
