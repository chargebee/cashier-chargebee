<?php

namespace Chargebee\Cashier\Tests\Feature;

use Carbon\Carbon;
use Chargebee\Cashier\Subscription;
use Chargebee\Cashier\SubscriptionItem;
use Chargebee\Cashier\Tests\Fixtures\User;

class ManagesSubscriptionsTest extends FeatureTestCase
{
    public function test_customer_on_trial(): void
    {
        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->addDay()]);
        $this->assertTrue($user->onTrial());

        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->subDay()]);
        $this->assertFalse($user->onTrial());
    }

    public function test_customer_has_expired_trial(): void
    {
        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->subDay()]);
        $this->assertTrue($user->hasExpiredTrial());

        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->addDay()]);
        $this->assertFalse($user->hasExpiredTrial());
    }

    public function test_customer_on_generic_trial(): void
    {
        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->addDay()]);
        $this->assertTrue($user->onGenericTrial());
    }

    public function test_scope_on_generic_trial(): void
    {
        User::factory()->create(['trial_ends_at' => Carbon::now()->addDay()]);
        $query = User::query();
        $query->onGenericTrial();
        $this->assertEquals(1, $query->count());
    }

    public function test_customer_has_expired_generic_trial(): void
    {
        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->subDay()]);
        $this->assertTrue($user->hasExpiredGenericTrial());
    }

    public function test_scope_has_expired_generic_trial(): void
    {
        User::factory()->create(['trial_ends_at' => Carbon::now()->subDay()]);
        $query = User::query();
        $query->hasExpiredGenericTrial();
        $this->assertEquals(1, $query->count());
    }

    public function test_customer_trial_ends_at(): void
    {
        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->addDays(5)]);
        $this->assertEquals(Carbon::now()->addDays(5)->toDateString(), $user->trialEndsAt()->toDateString());

        $user = User::factory()->create(['trial_ends_at' => null]);
        $this->assertNull($user->trialEndsAt());

        $user = User::factory()->create(['trial_ends_at' => Carbon::now()->addDays(5)]);
        Subscription::factory()->create(['user_id' => $user->id, 'type' => 'default', 'trial_ends_at' => Carbon::now()->addDays(5)]);
        $this->assertEquals(Carbon::now()->addDays(5)->toDateString(), $user->trialEndsAt('default')->toDateString());
    }

    public function test_customer_subscribed(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'ends_at' => null]);

        $this->assertTrue($user->subscribed());
    }

    public function test_customer_not_subscribed(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->subscribed());
    }

    public function test_customer_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'type' => 'default']);

        $this->assertEquals($subscription->id, $user->subscription('default')->id);
    }

    public function test_customer_subscriptions(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->subscriptions);
    }

    public function test_customer_subscribed_to_product(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        SubscriptionItem::factory()->create(['subscription_id' => $subscription->id, 'chargebee_product' => 'product_123']);

        $this->assertTrue($user->subscribedToProduct('product_123'));
    }

    public function test_customer_not_subscribed_to_product(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->subscribedToProduct('product_123'));
    }

    public function test_customer_subscribed_to_price(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'chargebee_price' => 'price_123']);
        SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_price' => 'price_123',
        ]);

        $this->assertTrue($user->subscribedToPrice('price_123'));
    }

    public function test_customer_not_subscribed_to_price(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->subscribedToPrice('price_123'));
    }

    public function test_customer_on_price(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->onPrice('price_123'));
    }

    public function test_customer_on_product(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_product' => 'product_123',
        ]);

        $this->assertTrue($user->onProduct('product_123'));
    }
}
