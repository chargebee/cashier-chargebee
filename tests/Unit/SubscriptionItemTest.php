<?php

namespace Chargebee\CashierChargebee\Tests\Unit;

use Chargebee\CashierChargebee\Subscription;
use Chargebee\CashierChargebee\SubscriptionItem;
use Chargebee\CashierChargebee\Tests\Feature\FeatureTestCase;

class SubscriptionItemTest extends FeatureTestCase
{
    public function test_subscription_relationship(): void
    {
        $subscription = Subscription::factory()->create();
        $subscriptionItem = SubscriptionItem::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertEquals($subscription->id, $subscriptionItem->subscription->id);
    }
}
