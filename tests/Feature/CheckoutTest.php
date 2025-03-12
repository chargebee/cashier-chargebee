<?php

namespace Chargebee\Cashier\Tests\Feature;

use Chargebee\Cashier\Checkout;
use Chargebee\Cashier\Session;
use ChargeBee\ChargeBee\Models\Coupon;

class CheckoutTest extends FeatureTestCase
{
    /**
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/home', fn () => 'Hello World!')->name('home');
    }

    public function test_customers_can_start_a_product_checkout_session()
    {
        $user = $this->createCustomer('can_start_a_product_checkout_session');

        $shirtPrice = $this->createPrice('T-shirt', amount: 1500);
        $carPrice = $this->createPrice('Car', 30000);

        $items = [$shirtPrice->id => 5, $carPrice->id];

        $checkout = $user->checkout($items, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_one_time', $checkout->type);
    }

    public function test_customers_can_start_a_product_checkout_session_with_a_coupon_applied()
    {
        $user = $this->createCustomer('can_start_checkout_session_with_coupon');

        $shirtPrice = $this->createPrice('T-shirt', 1500);

        $id = 'coupon_'.now()->timestamp;
        $coupon = Coupon::createForItems([
            'id' => $id,
            'name' => $id,
            'discountType' => 'fixed_amount',
            'discountAmount' => 500,
            'durationType' => 'one_time',
            'applyOn' => 'invoice_amount',
            'currencyCode' => config('cashier.currency'),
        ])->coupon();

        $checkout = $user->withCoupons([$coupon->id])
            ->checkout($shirtPrice->id, [
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_one_time', $checkout->type);
    }

    public function test_customers_can_start_a_one_off_charge_checkout_session()
    {
        $user = $this->createCustomer('can_start_one_off_checkout_session');

        $checkout = $user->checkoutCharge(1200, 'T-shirt', [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_one_time', $checkout->type);
    }

    public function test_customers_can_save_payment_details()
    {
        $user = $this->createCustomer('can_save_payment_details');

        $checkout = $user->checkout([], [
            'mode' => Session::MODE_SETUP,
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('manage_payment_sources', $checkout->type);
    }

    public function test_customers_can_start_a_subscription_checkout_session()
    {
        $user = $this->createCustomer('can_start_a_subscription_checkout_session');

        $price = $this->createSubscriptionPrice('Forge-Hobby', 1500);

        $checkout = $user->newSubscription('default', $price->id)
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_new', $checkout->type);

        $id = 'coupon_'.now()->timestamp;
        $coupon = Coupon::createForItems([
            'id' => $id,
            'name' => $id,
            'discountType' => 'fixed_amount',
            'discountAmount' => 500,
            'durationType' => 'one_time',
            'applyOn' => 'invoice_amount',
            'currencyCode' => config('cashier.currency'),
        ])->coupon();

        $checkout = $user->newSubscription('default', $price->id)
            ->withCoupons([$coupon->id])
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_new', $checkout->type);
    }

    public function test_guest_customers_can_start_a_checkout_session()
    {
        $shirtPrice = $this->createPrice('T-shirt', 1500);

        $checkout = Checkout::guest()->create($shirtPrice->id, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }
}
