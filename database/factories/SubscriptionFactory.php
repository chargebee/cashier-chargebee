<?php

namespace Chargebee\Cashier\Database\Factories;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Subscription;
use Chargebee\Resources\ItemPrice\ItemPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = Cashier::$customerModel;

        return [
            (new $model)->getForeignKey() => ($model)::factory(),
            'type' => 'default',
            'chargebee_id' => 'sub_'.Str::random(40),
            'chargebee_status' => 'active',
            'chargebee_price' => null,
            'quantity' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Add a price identifier to the model.
     */
    public function withPrice(ItemPrice|string $price): static
    {
        return $this->state([
            'chargebee_price' => $price instanceof ItemPrice ? $price->id : $price,
        ]);
    }
}
