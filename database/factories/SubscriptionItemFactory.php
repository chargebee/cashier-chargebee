<?php

namespace Chargebee\Cashier\Database\Factories;

use Chargebee\Cashier\Subscription;
use Chargebee\Cashier\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = SubscriptionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'chargebee_product' => 'prod_'.Str::random(40),
            'chargebee_price' => 'price_'.Str::random(40),
            'quantity' => null,
        ];
    }
}
