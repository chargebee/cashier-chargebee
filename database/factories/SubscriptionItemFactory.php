<?php

namespace Laravel\CashierChargebee\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\CashierChargebee\Subscription;
use Laravel\CashierChargebee\SubscriptionItem;

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
            'chargebee_id' => 'si_'.Str::random(40),
            'chargebee_product' => 'prod_'.Str::random(40),
            'chargebee_price' => 'price_'.Str::random(40),
            'quantity' => null,
        ];
    }
}
