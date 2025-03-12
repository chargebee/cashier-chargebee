<?php

namespace Chargebee\Cashier\Tests\Unit;

use Chargebee\Cashier\Subscription;
use Chargebee\Cashier\SubscriptionItem;
use Chargebee\Cashier\Tests\Feature\FeatureTestCase;

class SubscriptionItemTest extends FeatureTestCase
{
    public function test_subscription_relationship(): void
    {
        $subscription = Subscription::factory()->create();
        $subscriptionItem = SubscriptionItem::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertEquals($subscription->id, $subscriptionItem->subscription->id);
    }
}
