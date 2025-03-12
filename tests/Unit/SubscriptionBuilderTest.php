<?php

namespace Chargebee\CashierChargebee\Tests\Unit;

use Carbon\Carbon;
use Chargebee\CashierChargebee\SubscriptionBuilder;
use Chargebee\CashierChargebee\Tests\Fixtures\User;
use Chargebee\CashierChargebee\Tests\TestCase;
use Exception;

class SubscriptionBuilderTest extends TestCase
{
    public function test_it_can_be_instantiated(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default', [
            'price_foo',
            ['itemPriceId' => 'price_bux'],
            ['itemPriceId' => 'price_bar', 'quantity' => 1],
            ['itemPriceId' => 'price_baz', 'quantity' => 2],
        ]);

        $this->assertSame([
            'price_foo' => ['itemPriceId' => 'price_foo', 'quantity' => 1],
            'price_bux' => ['itemPriceId' => 'price_bux', 'quantity' => 1],
            'price_bar' => ['itemPriceId' => 'price_bar', 'quantity' => 1],
            'price_baz' => ['itemPriceId' => 'price_baz', 'quantity' => 2],
        ], $builder->getItems());
    }

    public function test_create_without_price_throws_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('At least one price is required when starting subscriptions.');

        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->create();
    }

    public function test_price_must_include_item_price_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each price must include an "itemPriceId" key.');

        new SubscriptionBuilder(new User, 'default', [
            ['quantity' => 3],
        ]);
    }

    public function test_price(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->price('price_xyz', 2);

        $this->assertSame([
            'price_xyz' => ['itemPriceId' => 'price_xyz', 'quantity' => 2],
        ], $builder->getItems());
    }

    public function test_metered_price(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->meteredPrice('metered_price_abc');

        $this->assertSame([
            'metered_price_abc' => ['itemPriceId' => 'metered_price_abc'],
        ], $builder->getItems());
    }

    public function test_quantity(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default', 'price_123');
        $builder->quantity(5);

        $this->assertSame([
            'price_123' => ['itemPriceId' => 'price_123', 'quantity' => 5],
        ], $builder->getItems());
    }

    public function test_quantity_with_no_price_and_multiple_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price is required when creating subscriptions with multiple prices.');

        $builder = new SubscriptionBuilder(new User, 'default', ['price_123', 'price_321']);
        $builder->quantity(5);
    }

    public function test_trial_days(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->trialDays(10);

        $expectedDate = Carbon::now()->addDays(10);
        $actualDate = $this->getProtectedProperty($builder, 'trialExpires');

        $this->assertInstanceOf(Carbon::class, $actualDate);
        $this->assertSame($expectedDate->toDateString(), $actualDate->toDateString());
    }

    public function test_trial_until(): void
    {
        $trialUntil = Carbon::now()->addDays(15);
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->trialUntil($trialUntil);

        $this->assertSame($trialUntil, $this->getProtectedProperty($builder, 'trialExpires'));
    }

    public function test_skip_trial(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->skipTrial();

        $this->assertTrue($this->getProtectedProperty($builder, 'skipTrial'));
    }

    public function test_with_metadata(): void
    {
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->withMetadata($metadata);

        $this->assertSame($metadata, $this->getProtectedProperty($builder, 'metadata'));
    }
}
