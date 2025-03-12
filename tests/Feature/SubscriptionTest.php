<?php

namespace Chargebee\CashierChargebee\Tests\Feature;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Coupon;
use ChargeBee\ChargeBee\Models\Invoice;
use ChargeBee\ChargeBee\Models\Item;
use ChargeBee\ChargeBee\Models\ItemFamily;
use ChargeBee\ChargeBee\Models\ItemPrice;
use ChargeBee\ChargeBee\Models\PaymentSource;
use ChargeBee\ChargeBee\Models\Subscription as ChargebeeSubscription;
use ChargeBee\ChargeBee\Models\UnbilledCharge;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Chargebee\CashierChargebee\Estimate;
use Chargebee\CashierChargebee\Exceptions\SubscriptionUpdateFailure;
use Chargebee\CashierChargebee\Subscription;
use Chargebee\CashierChargebee\SubscriptionBuilder;
use Chargebee\CashierChargebee\Transaction;

class SubscriptionTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $itemFamilyId;

    /**
     * @var string
     */
    protected static $firstItemId;

    /**
     * @var string
     */
    protected static $secondItemId;

    /**
     * @var string
     */
    protected static $thirdItemId;

    /**
     * @var string
     */
    protected static $threeDSecureItemId;

    /**
     * @var string
     */
    protected static $firstMeteredItemId;

    /**
     * @var string
     */
    protected static $secondMeteredItemId;

    /**
     * @var string
     */
    protected static $firstPriceId;

    /**
     * @var string
     */
    protected static $threeDSecurePriceId;

    /**
     * @var string
     */
    protected static $secondPriceId;

    /**
     * @var string
     */
    protected static $thirdPriceId;

    /**
     * @var string
     */
    protected static $firstMeteredPriceId;

    /**
     * @var string
     */
    protected static $secondMeteredPriceId;

    /**
     * @var string
     */
    protected static $firstPriceWithTrial;

    /**
     * @var string
     */
    protected static $couponId;

    protected function setUp(): void
    {
        parent::setUp();

        static::$itemFamilyId = ItemFamily::create([
            'id' => Str::random(40),
            'name' => Str::random(40),
        ])->itemFamily()->id;

        static::$firstItemId = Item::create([
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'plan',
            'itemFamilyId' => static::$itemFamilyId,
        ])->item()->id;

        static::$firstPriceId = ItemPrice::create([
            'id' => Str::random(40),
            'itemId' => static::$firstItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 5000,
            'externalName' => 'Test ItemPrice 1',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ])->itemPrice()->id;

        static::$secondItemId = Item::create([
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'addon',
            'itemFamilyId' => static::$itemFamilyId,
        ])->item()->id;

        static::$secondPriceId = ItemPrice::create([
            'id' => Str::random(40),
            'itemId' => static::$secondItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 2000,
            'externalName' => 'Test ItemPrice 2',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ])->itemPrice()->id;

        static::$thirdItemId = Item::create([
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'plan',
            'itemFamilyId' => static::$itemFamilyId,
        ])->item()->id;

        static::$thirdPriceId = ItemPrice::create([
            'id' => Str::random(40),
            'itemId' => static::$thirdItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 3000,
            'externalName' => 'Test ItemPrice 3',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ])->itemPrice()->id;

        static::$firstMeteredItemId = Item::create([
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'plan',
            'itemFamilyId' => static::$itemFamilyId,
            'metered' => true,
        ])->item()->id;

        static::$firstMeteredPriceId = ItemPrice::create([
            'id' => Str::random(40),
            'itemId' => static::$firstMeteredItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 5000,
            'externalName' => 'Test metered ItemPrice 1',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ])->itemPrice()->id;

        static::$secondMeteredItemId = Item::create([
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'addon',
            'itemFamilyId' => static::$itemFamilyId,
            'metered' => true,
        ])->item()->id;

        static::$secondMeteredPriceId = ItemPrice::create([
            'id' => Str::random(40),
            'itemId' => static::$secondMeteredItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 1000,
            'externalName' => 'Test metered ItemPrice 2',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ])->itemPrice()->id;

        static::$firstPriceWithTrial = ItemPrice::create([
            'id' => Str::random(40),
            'itemId' => static::$firstItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 5000,
            'externalName' => 'Test ItemPrice 1',
            'periodUnit' => 'year',
            'period' => 1,
            'currencyCode' => 'EUR',
            'trialPeriod' => 7,
            'trialPeriodUnit' => 'day',
        ])->itemPrice()->id;

        static::$couponId = Coupon::createForItems([
            'id' => Str::random(40),
            'name' => Str::random(40),
            'discountPercentage' => 10,
            'discountType' => 'PERCENTAGE',
            'durationType' => 'FOREVER',
            'applyOn' => 'EACH_SPECIFIED_ITEM',
            'itemConstraints' => [
                [
                    'constraint' => 'ALL',
                    'itemType' => 'PLAN',
                ],
            ],
        ])->coupon()->id;
    }

    public function test_subscription_can_be_created_and_status_synced(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_created');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->withMetadata(['type' => 'main'])
            ->create($paymentSource);

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull(($subscription = $user->subscription('main'))->chargebee_id);

        $retrievedSubscription = $subscription->asChargebeeSubscription();
        $this->assertSame($subscription->chargebee_id, $retrievedSubscription->id);

        $subscription->chargebee_status = null;
        $subscription->syncChargebeeStatus();
        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
    }

    public function test_subscriptions_can_be_updated(): void
    {
        $user = $this->createCustomer('subscriptions_can_be_updated');

        $subscription = $user->newSubscription('main', static::$firstPriceId)->add();

        $updateOptions = [
            'subscriptionItems' => [
                [
                    'itemPriceId' => static::$firstPriceId,
                    'quantity' => 4,
                    'unitPrice' => 2000,
                ],
            ],
        ];

        $updatedSubscription = $subscription->updateChargebeeSubscription($updateOptions);

        $this->assertSame(static::$firstPriceId, $updatedSubscription->subscriptionItems[0]->itemPriceId);
        $this->assertSame(4, $updatedSubscription->subscriptionItems[0]->quantity);
        $this->assertSame(2000, $updatedSubscription->subscriptionItems[0]->unitPrice);
    }

    public function test_subscription_can_be_cancelled_at_the_end_of_the_billing_period(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancel();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->currentTermEnd), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_at_specific_date(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelAt(Carbon::now()->addDay());

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->cancelledAt), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_now(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelNow();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame('cancelled', $retrievedSubscription->status);
        $this->assertEquals('cancelled', $subscription->chargebee_status);
        $this->assertTrue($subscription->ends_at->isToday());
    }

    public function test_subscription_can_be_cancelled_now_and_invoiced(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelNowAndInvoice();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame('cancelled', $retrievedSubscription->status);
        $this->assertEquals('cancelled', $subscription->chargebee_status);
        $this->assertTrue($subscription->ends_at->isToday());
    }

    public function test_subscription_can_be_resumed(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_resumed');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        ChargebeeSubscription::pause($subscription->chargebee_id, [
            'pauseOption' => 'immediately',
        ])->subscription();

        $subscription->syncChargebeeStatus();

        $this->assertSame('paused', $subscription->chargebee_status);

        $subscription->resume();

        $this->assertSame('active', $subscription->chargebee_status);
    }

    public function test_create_subscription_with_trial(): void
    {
        $user = $this->createCustomer('test_create_subscription_with_trial');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $user->newSubscription('main', static::$firstPriceId)
            ->trialDays(7)
            ->create($paymentSource);

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->onTrial());
        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);
        $this->assertEquals(Carbon::today()->addDays(7)->day, $user->trialEndsAt('main')->day);
    }

    public function test_create_subscription_with_skipped_trial(): void
    {
        $user = $this->createCustomer('test_create_subscription_with_skipped_trial');
        $user->createAsChargebeeCustomer();

        $user->newSubscription('main', static::$firstPriceWithTrial)
            ->skipTrial()
            ->create();

        $subscription = $user->subscription('main');

        $this->assertFalse($subscription->onTrial());
        $this->assertSame('active', $subscription->asChargebeeSubscription()->status);
    }

    public function test_trial_can_be_extended(): void
    {
        $user = $this->createCustomer('test_trial_can_be_extended');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->trialDays(7)
            ->create($paymentSource);

        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);

        $subscription->extendTrial($trialEndsAt = now()->addDays(8)->floor());

        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);
        $this->assertTrue($trialEndsAt->equalTo($subscription->trial_ends_at));
        $this->assertEquals($subscription->asChargebeeSubscription()->trialEnd, $trialEndsAt->getTimestamp());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Extending a subscription's trial requires a date in the future.");

        $subscription->extendTrial($trialEndsAt = now()->subDays(8)->floor());
    }

    public function test_cannot_extend_trial_for_active_subscription(): void
    {
        $user = $this->createCustomer('test_cannot_extend_trial_for_active_subscription');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $this->expectException(SubscriptionUpdateFailure::class);
        $this->expectExceptionMessage("Cannot extend trial for a subscription with status 'active'.");

        $subscription->extendTrial(now()->addDays(7));
    }

    public function test_trial_can_be_ended(): void
    {
        $user = $this->createCustomer('test_trial_can_be_ended');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->trialDays(10)
            ->create($paymentSource);

        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);

        $subscription->endTrial();

        $this->assertNull($subscription->trial_ends_at);
        $this->assertSame('active', $subscription->asChargebeeSubscription()->status);
    }

    public function test_end_trial_does_nothing_if_not_on_trial(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertNull($subscription->trial_ends_at);

        $returnedSubscription = $subscription->endTrial();

        $this->assertSame($subscription, $returnedSubscription);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_price_can_be_added_and_removed(): void
    {
        $user = $this->createCustomer('test_price_can_be_added_and_removed');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        // Test adding second price (with default quantity = 1)
        $subscription->addPriceAndInvoice(static::$secondPriceId);

        $this->assertCount(2, $subscription->items);
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$firstPriceId));
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$secondPriceId));

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeSubscriptionItems = collect($chargebeeSubscription->subscriptionItems);

        $this->assertCount(2, $chargebeeSubscriptionItems);
        $this->assertTrue($chargebeeSubscriptionItems->pluck('itemPriceId')->contains(static::$firstPriceId));

        $secondPriceItem = $chargebeeSubscriptionItems->firstWhere('itemPriceId', static::$secondPriceId);
        $this->assertNotNull($secondPriceItem);
        $this->assertEquals(1, $secondPriceItem->quantity);

        // Test adding the same price second time (should throw an exception)
        try {
            $subscription->addPrice(static::$secondPriceId);
        } catch (Exception $e) {
            $this->assertInstanceOf(SubscriptionUpdateFailure::class, $e);
            $this->assertSame(
                "The price \"$secondPriceItem->itemPriceId\" is already attached to subscription \"{$subscription->chargebee_id}\".",
                $e->getMessage()
            );
        }

        // Test removing price
        $subscription->removePrice(static::$secondPriceId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$firstPriceId, $subscription->items->first()->chargebee_price);

        $this->assertCount(1, $chargebeeSubscription->subscriptionItems);
        $this->assertEquals(static::$firstPriceId, $chargebeeSubscription->subscriptionItems[0]->itemPriceId);

        // Test removing last price (should throw an exception)
        try {
            $subscription->removePrice(static::$firstPriceId);
        } catch (Exception $e) {
            $this->assertInstanceOf(SubscriptionUpdateFailure::class, $e);
            $this->assertSame(
                "The price on subscription \"{$subscription->chargebee_id}\" cannot be removed because it is the last one.",
                $e->getMessage()
            );
        }
    }

    public function test_metered_price_can_be_added_and_removed(): void
    {
        $user = $this->createCustomer('test_metered_price_can_be_added');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$firstMeteredPriceId)
            ->create($paymentSource);

        // Test adding metered price
        $subscription->addMeteredPriceAndInvoice(static::$secondMeteredPriceId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $this->assertCount(2, $subscription->items);
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$firstMeteredPriceId));
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$secondMeteredPriceId));

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeSubscriptionItems = collect($chargebeeSubscription->subscriptionItems);

        $this->assertCount(2, $chargebeeSubscriptionItems);
        $this->assertTrue($chargebeeSubscriptionItems->pluck('itemPriceId')->contains(static::$firstMeteredPriceId));

        $secondPriceItem = $chargebeeSubscriptionItems->firstWhere('itemPriceId', static::$secondMeteredPriceId);
        $this->assertNotNull($secondPriceItem);
        $this->assertNull($secondPriceItem->quantity ?? null);

        // Test removing metered price
        $subscription->removePrice(static::$secondMeteredPriceId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$firstMeteredPriceId, $subscription->items->first()->chargebee_price);

        $this->assertCount(1, $chargebeeSubscription->subscriptionItems);
        $this->assertEquals(static::$firstMeteredPriceId, $chargebeeSubscription->subscriptionItems[0]->itemPriceId);
    }

    public function test_usage_can_be_reported_and_retrieved(): void
    {
        $user = $this->createCustomer('test_usage_can_be_reported_and_retrieved');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->meteredPrice(static::$secondMeteredPriceId)
            ->create($paymentSource);

        try {
            $subscription->reportUsage();
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);

            $this->assertSame(
                'This method requires a price argument since the subscription has multiple prices.',
                $e->getMessage()
            );
        }

        $subscription->reportUsageFor(static::$secondMeteredPriceId, 20);
        $usage = $subscription->usageRecordsFor(static::$secondMeteredPriceId)->first();

        $this->assertEquals(20, $usage->quantity);

        try {
            $subscription->reportUsageFor(static::$firstPriceId);
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidRequestException::class, $e);
        }

        try {
            $subscription->usageRecords();
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);

            $this->assertSame(
                'This method requires a price argument since the subscription has multiple prices.',
                $e->getMessage()
            );
        }
    }

    public function test_coupon_can_be_applied(): void
    {
        $user = $this->createCustomer('test_usage_can_be_reported_and_retrieved');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $subscription->applyCoupon(static::$couponId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $coupons = $chargebeeSubscription->coupons;

        $this->assertCount(1, $coupons);
        $this->assertEquals(static::$couponId, $coupons[0]->couponId);
    }

    public function test_item_quantity_can_be_updated_from_subscription(): void
    {
        $user = $this->createCustomer('test_item_quantity_can_be_updated');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main')
            ->price(static::$firstPriceId, 2)
            ->create($paymentSource);

        // Initial quantity = 2
        $this->assertCount(1, $subscription->items);
        $this->assertEquals(2, $subscription->items->first()->quantity);
        $this->assertEquals(2, $subscription->quantity);

        // Decrement quantity by 1
        $subscription->decrementQuantity(1);
        $subscription->refresh();

        $this->assertEquals(1, $subscription->items->first()->quantity);
        $this->assertEquals(1, $subscription->quantity);

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $this->assertEquals(1, $chargebeeItem->quantity);

        // Add second price and increment its quantity by 2
        $subscription->addPrice(static::$secondPriceId);
        $subscription->incrementAndInvoice(2, static::$secondPriceId);
        $subscription->refresh();

        $this->assertCount(2, $subscription->items);

        $firstItem = $subscription->items->firstWhere('chargebee_price', static::$firstPriceId);
        $secondItem = $subscription->items->firstWhere('chargebee_price', static::$secondPriceId);

        $this->assertEquals(1, $firstItem->quantity);
        $this->assertEquals(3, $secondItem->quantity);
        $this->assertNull($subscription->quantity);

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeFirstItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $chargebeeSecondItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$secondPriceId);

        $this->assertEquals(1, $chargebeeFirstItem->quantity);
        $this->assertEquals(3, $chargebeeSecondItem->quantity);

        // Now that there are two prices we can't use updateQuantity without $price parameter
        $this->expectException(InvalidArgumentException::class);
        $subscription->updateQuantity(1);
    }

    public function test_item_quantity_can_be_updated_from_subscription_item(): void
    {
        $user = $this->createCustomer('test_item_quantity_can_be_updated');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main')
            ->price(static::$firstPriceId, 2)
            ->create($paymentSource);

        $firstItem = $subscription->items->first();

        // Initial quantity = 2
        $this->assertCount(1, $subscription->items);
        $this->assertEquals(2, $firstItem->quantity);
        $this->assertEquals(2, $subscription->quantity);

        // Decrement quantity by 1
        $firstItem->decrementQuantity(1);
        $subscription->refresh();
        $firstItem->refresh();

        $this->assertEquals(1, $firstItem->quantity);
        $this->assertEquals(1, $subscription->quantity);

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $this->assertEquals(1, $chargebeeItem->quantity);

        // Add second price and increment its quantity by 2
        $subscription->addPrice(static::$secondPriceId);

        $secondItem = $subscription->items->firstWhere('chargebee_price', static::$secondPriceId);
        $secondItem->incrementAndInvoice(2);
        $subscription->refresh();
        $secondItem->refresh();

        $this->assertCount(2, $subscription->items);

        $this->assertEquals(1, $firstItem->quantity);
        $this->assertEquals(3, $secondItem->quantity);
        $this->assertNull($subscription->quantity);

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeFirstItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $chargebeeSecondItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$secondPriceId);

        $this->assertEquals(1, $chargebeeFirstItem->quantity);
        $this->assertEquals(3, $chargebeeSecondItem->quantity);
    }

    public function test_swapping_subscription_and_preserving_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $subscription = $subscription->swapAndInvoice(static::$thirdPriceId);

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(5, $subscription->quantity);

        $item = $subscription->items->first();

        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(5, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(5, $chargebeeItem->quantity);
    }

    public function test_swapping_subscription_and_adopting_new_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $subscription = $subscription->swap([static::$thirdPriceId => ['quantity' => 3]]);

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(3, $subscription->quantity);

        $item = $subscription->items->first();

        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(3, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(3, $chargebeeItem->quantity);
    }

    public function test_swapping_subscription_item_and_preserving_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription_item');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $item = $subscription->items->first()->swap(static::$thirdPriceId);
        $subscription->refresh();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(5, $subscription->quantity);
        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(5, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(5, $chargebeeItem->quantity);
    }

    public function test_swapping_subscription_item_and_adopting_new_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription_item');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $item = $subscription->items->first()->swap(static::$thirdPriceId, ['quantity' => 3]);
        $subscription->refresh();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(3, $subscription->quantity);
        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(3, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(3, $chargebeeItem->quantity);
    }

    public function test_swap_on_item_with_prorate(): void
    {
        $user = $this->createCustomer('test_swap_on_item_with_prorate');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $subscription->items->first()->prorate()->swapAndInvoice(static::$thirdPriceId);

        $result = Invoice::all([
            'subscriptionId[is]' => $subscription->chargebee_id,
            'limit' => 1,
            'sortBy[desc]' => 'date',
        ]);

        $latestInvoice = $result[0]->invoice();

        $this->assertNotNull($latestInvoice);
        $this->assertGreaterThan(0, count($latestInvoice->lineItems));

        $hasProratedCharge = collect($latestInvoice->lineItems)->contains(function ($item) {
            return str_contains(strtolower($item->description), 'prorated');
        });

        $this->assertTrue($hasProratedCharge);
    }

    public function test_swap_on_item_with_no_prorate(): void
    {
        $user = $this->createCustomer('test_swap_on_item_with_no_prorate');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $initialInvoiceCount = count(Invoice::all(['subscriptionId[is]' => $subscription->chargebee_id]));
        $initialUnbilledCount = count(UnbilledCharge::all(['subscriptionId[is]' => $subscription->chargebee_id]));

        $subscription->items->first()->noProrate()->swap(static::$thirdPriceId);

        $finalInvoiceCount = count(Invoice::all(['subscriptionId[is]' => $subscription->chargebee_id]));
        $finalUnbilledCount = count(UnbilledCharge::all(['subscriptionId[is]' => $subscription->chargebee_id]));

        $this->assertEquals($initialInvoiceCount, $finalInvoiceCount);
        $this->assertEquals($initialUnbilledCount, $finalUnbilledCount);
    }

    public function test_increment_quantity_with_prorate(): void
    {
        $user = $this->createCustomer('test_increment_quantity_with_prorate');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $subscription->prorate()->incrementAndInvoice(4, static::$firstPriceId);

        $result = Invoice::all([
            'subscriptionId[is]' => $subscription->chargebee_id,
            'limit' => 1,
            'sortBy[desc]' => 'date',
        ]);

        $latestInvoice = $result[0]->invoice();

        $this->assertNotNull($latestInvoice);
        $this->assertGreaterThan(0, count($latestInvoice->lineItems));

        $hasProratedCharge = collect($latestInvoice->lineItems)->contains(function ($item) {
            return str_contains(strtolower($item->description), 'prorated');
        });

        $this->assertTrue($hasProratedCharge);
    }

    public function test_increment_quantity_with_no_prorate(): void
    {
        $user = $this->createCustomer('test_increment_quantity_with_no_prorate');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $initialInvoiceCount = count(Invoice::all(['subscriptionId[is]' => $subscription->chargebee_id]));
        $initialUnbilledCount = count(UnbilledCharge::all(['subscriptionId[is]' => $subscription->chargebee_id]));

        $subscription->noProrate()->updateQuantity(5, static::$firstPriceId);

        $finalInvoiceCount = count(Invoice::all(['subscriptionId[is]' => $subscription->chargebee_id]));
        $finalUnbilledCount = count(UnbilledCharge::all(['subscriptionId[is]' => $subscription->chargebee_id]));

        $this->assertEquals($initialInvoiceCount, $finalInvoiceCount);
        $this->assertEquals($initialUnbilledCount, $finalUnbilledCount);
    }

    public function test_as_chargebee_subscription_item_throws_exception_when_not_found(): void
    {
        $user = $this->createCustomer('test_as_chargebee_subscription_item');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $subscription->items()->create([
            'chargebee_product' => 'fake_product',
            'chargebee_price' => static::$secondPriceId,
            'quantity' => 1,
        ]);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("Subscription item with price '".static::$secondPriceId."' not found in Chargebee.");

        $subscription->findItemOrFail(static::$secondPriceId)->asChargebeeSubscriptionItem();
    }

    public function test_existing_subscription_is_returned_without_creating_new(): void
    {
        $user = $this->createCustomer('test_existing_subscription');

        $existingSubscription = $user->subscriptions()->create([
            'type' => 'main',
            'chargebee_id' => 'test_chargebee_id_123',
            'chargebee_status' => 'active',
        ]);

        $mockChargebeeSubscription = new ChargebeeSubscription([
            'id' => 'test_chargebee_id_123',
            'status' => 'active',
        ]);

        $builder = $user->newSubscription('main', static::$firstPriceId);

        $reflectedMethod = new \ReflectionMethod(
            SubscriptionBuilder::class,
            'createSubscription'
        );

        $retrievedSubscription = $reflectedMethod->invokeArgs($builder, [$mockChargebeeSubscription]);

        $this->assertSame($existingSubscription->id, $retrievedSubscription->id);
        $this->assertSame($existingSubscription->chargebee_id, $retrievedSubscription->chargebee_id);

        $this->assertEquals(1, $user->subscriptions()->count());
    }

    public function test_skip_trial_and_swap(): void
    {
        $user = $this->createCustomer('test_skip_trial_and_swap');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->trialDays(5)
            ->create(paymentSource: $paymentSource);

        $this->assertTrue($subscription->onTrial());

        $subscription = $subscription->skipTrial()->swapAndInvoice(static::$thirdPriceId);

        $this->assertFalse($subscription->onTrial());
    }

    public function test_upcoming_invoice()
    {
        $user = $this->createCustomer('subscription_upcoming_invoice');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $invoice = $subscription->upcomingInvoice();
        $this->assertInstanceOf(Estimate::class, $invoice);
    }

    public function test_preview_invoice()
    {
        $user = $this->createCustomer('subscription_preview_invoice');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $estimate = $subscription->previewInvoice(static::$thirdPriceId);

        $this->assertSame(3000, $estimate->total);
    }

    public function test_retrieve_the_latest_payment_for_a_subscription()
    {
        $user = $this->createCustomer('retrieve_the_latest_payment_for_a_subscription');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource, [], [
                'autoCollection' => 'on',
            ]);

        $latestPayment = $subscription->latestPayment();
        $this->assertInstanceOf(Transaction::class, $latestPayment);
        $this->assertSame(5000, $latestPayment->rawAmount());
    }

    public function skip_test_invoice_subscription_directly()
    {
        $user = $this->createCustomer('invoice_subscription_directly');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $subscription->updateQuantity(3);

        $invoice = $subscription->invoice();

        $latestInvoice = $subscription->latestInvoice();

        $this->assertEquals($latestInvoice->id, $invoice->id);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(15000, $invoice->total);
    }

    public function skip_test_invoice_subscription_directly_fails()
    {
        $user = $this->createCustomer('invoice_subscription_directly_fails');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createFailedCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $subscription->updateQuantity(3);

        $invoice = $subscription->invoice();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame(15000, $invoice->total);
    }

    private function createFailedCard(Model $user): ?PaymentSource
    {
        return PaymentSource::createCard(
            [
                'customer_id' => $user->chargebeeId(),
                'card' => [
                    'number' => '4005 5192 0000 0004',
                    'cvv' => '123',
                    'expiry_year' => date('Y', strtotime('+ 1 year')),
                    'expiry_month' => date('m', strtotime('+ 1 year')),
                ],
            ]
        )->paymentSource();
    }

    private function createCard(Model $user): ?PaymentSource
    {
        return PaymentSource::createCard(
            [
                'customer_id' => $user->chargebeeId(),
                'card' => [
                    'number' => '4111 1111 1111 1111',
                    'cvv' => '123',
                    'expiry_year' => date('Y', strtotime('+ 1 year')),
                    'expiry_month' => date('m', strtotime('+ 1 year')),
                ],
            ]
        )->paymentSource();
    }

    private function create3DS2Card(Model $user): ?PaymentSource
    {
        return PaymentSource::createCard(
            [
                'customer_id' => $user->chargebeeId(),
                'card' => [
                    'number' => '4556 7610 2998 3886',
                    'cvv' => '123',
                    'expiry_year' => date('Y', strtotime('+ 1 year')),
                    'expiry_month' => date('m', strtotime('+ 1 year')),
                ],
            ]
        )->paymentSource();
    }
}
