<?php

namespace Chargebee\CashierChargebee\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Chargebee\CashierChargebee\Subscription;
use Chargebee\CashierChargebee\SubscriptionItem;
use Chargebee\CashierChargebee\Tests\Feature\FeatureTestCase;
use Chargebee\CashierChargebee\Tests\Fixtures\User;

class SubscriptionTest extends FeatureTestCase
{
    public function test_prorate_on_subscription_create(): void
    {
        $subscription = (new Subscription())->prorate();

        $this->assertEquals(true, $subscription->prorateBehavior());
    }

    public function test_no_prorate_on_subscription_create(): void
    {
        $subscription = (new Subscription())->noProrate();

        $this->assertEquals(false, $subscription->prorateBehavior());
    }

    public function test_prorate_behavior_on_subscription_create(): void
    {
        $subscription = (new Subscription())->setProrationBehavior(true);

        $this->assertEquals(true, $subscription->prorateBehavior());
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $subscription->user->id);
    }

    public function test_has_multiple_prices(): void
    {
        $subscription = Subscription::factory()->create(['chargebee_price' => null]);
        $this->assertTrue($subscription->hasMultiplePrices());
    }

    public function test_has_single_price(): void
    {
        $subscription = Subscription::factory()->create(['chargebee_price' => 'price_123']);
        $this->assertTrue($subscription->hasSinglePrice());
    }

    public function test_has_product(): void
    {
        $subscription = Subscription::factory()->create();
        $item = SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_product' => 'product_123',
        ]);

        $this->assertTrue($subscription->hasProduct('product_123'));
        $this->assertFalse($subscription->hasProduct('product_456'));
    }

    public function test_has_price(): void
    {
        $subscription = Subscription::factory()->create(['chargebee_price' => null]);
        $item = SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_price' => 'price_123',
        ]);

        $this->assertTrue($subscription->hasPrice('price_123'));
        $this->assertFalse($subscription->hasPrice('price_456'));
    }

    public function test_find_item_or_fail_with_existing_item(): void
    {
        $subscription = Subscription::factory()->create();
        $item = SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_price' => 'price_123',
        ]);

        $foundItem = $subscription->findItemOrFail('price_123');
        $this->assertEquals($item->id, $foundItem->id);
    }

    public function test_find_item_or_fail_with_no_item(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $subscription = Subscription::factory()->create();
        $subscription->findItemOrFail('nonexistent_price');
    }

    public function test_valid(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertTrue($subscription->valid());

        $subscription = Subscription::factory()->create();
        $subscription->markAsCanceled();

        $this->assertFalse($subscription->valid());
    }

    public function test_active(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertTrue($subscription->active());
    }

    public function test_scope_active(): void
    {
        Subscription::factory()->create();
        $query = Subscription::query();
        $query->active();

        $this->assertEquals(1, $query->count());
    }

    public function test_recurring(): void
    {
        $subscription = Subscription::factory()->create(['trial_ends_at' => null, 'ends_at' => null]);

        $this->assertTrue($subscription->recurring());
    }

    public function test_canceled(): void
    {
        $subscription = Subscription::factory()->create(['ends_at' => Carbon::now()]);

        $this->assertTrue($subscription->canceled());
    }

    public function test_ended(): void
    {
        $subscription = Subscription::factory()->create(['ends_at' => Carbon::now()->subDay()]);

        $this->assertTrue($subscription->ended());
    }

    public function test_on_trial(): void
    {
        $subscription = Subscription::factory()->create(['trial_ends_at' => Carbon::now()->addDay()]);

        $this->assertTrue($subscription->onTrial());
    }

    public function test_has_expired_trial(): void
    {
        $subscription = Subscription::factory()->create(['trial_ends_at' => Carbon::now()->subDay()]);

        $this->assertTrue($subscription->hasExpiredTrial());
    }

    public function test_on_grace_period(): void
    {
        $subscription = Subscription::factory()->create(['ends_at' => Carbon::now()->addDay()]);

        $this->assertTrue($subscription->onGracePeriod());
    }

    public function test_scope_recurring(): void
    {
        Subscription::factory()->create(['trial_ends_at' => null, 'ends_at' => null]);
        $query = Subscription::query();
        $query->recurring();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_canceled(): void
    {
        Subscription::factory()->create(['ends_at' => Carbon::now()]);
        $query = Subscription::query();
        $query->canceled();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_not_canceled(): void
    {
        Subscription::factory()->create(['ends_at' => null]);
        $query = Subscription::query();
        $query->notCanceled();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_ended(): void
    {
        Subscription::factory()->create(['ends_at' => Carbon::now()->subDay()]);
        $query = Subscription::query();
        $query->ended();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_on_trial(): void
    {
        Subscription::factory()->create(['trial_ends_at' => Carbon::now()->addDay()]);
        $query = Subscription::query();
        $query->onTrial();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_not_on_trial(): void
    {
        Subscription::factory()->create(['trial_ends_at' => null]);
        $query = Subscription::query();
        $query->notOnTrial();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_expired_trial(): void
    {
        Subscription::factory()->create(['trial_ends_at' => Carbon::now()->subDay()]);
        $query = Subscription::query();
        $query->expiredTrial();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_on_grace_period(): void
    {
        Subscription::factory()->create(['ends_at' => Carbon::now()->addDay()]);
        $query = Subscription::query();
        $query->onGracePeriod();

        $this->assertEquals(1, $query->count());
    }

    public function test_scope_not_on_grace_period(): void
    {
        Subscription::factory()->create(['ends_at' => Carbon::now()->subDay()]);
        $query = Subscription::query();
        $query->notOnGracePeriod();

        $this->assertEquals(1, $query->count());
    }

    public function test_paused(): void
    {
        $subscription = Subscription::factory()->create(['chargebee_status' => 'paused']);

        $this->assertTrue($subscription->paused());
    }

    public function test_mark_as_canceled(): void
    {
        $subscription = Subscription::factory()->create(['chargebee_status' => 'active', 'ends_at' => null]);

        $subscription->markAsCanceled();

        $this->assertEquals('cancelled', $subscription->chargebee_status);
        $this->assertNotNull($subscription->ends_at);
        $this->assertTrue($subscription->ends_at->isToday());
    }

    public function test_guard_against_multiple_prices(): void
    {
        $subscription = Subscription::factory()->create(['chargebee_price' => null]);

        $this->expectException(InvalidArgumentException::class);

        $subscription->guardAgainstMultiplePrices();
    }
}
