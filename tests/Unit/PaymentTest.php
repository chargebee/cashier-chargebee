<?php

namespace Chargebee\Cashier\Tests\Unit;

use Chargebee\Cashier\Exceptions\IncompletePayment;
use Chargebee\Cashier\Payment;
use Chargebee\Cashier\Tests\TestCase;
use Chargebee\Resources\PaymentIntent\PaymentIntent;

class PaymentTest extends TestCase
{
    public function test_as_chargebee_payment_intent(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 5000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
            "currency_code" => 'EUR',
        ]);
        $payment = new Payment($paymentIntent);

        $this->assertSame($paymentIntent, $payment->asChargebeePaymentIntent());
    }

    public function test_to_array(): void
    {
        $dummyPaymentIntent = [
            'id' => 'id_123',
            'amount' => 5000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
        ];
        $paymentIntent = PaymentIntent::from($dummyPaymentIntent);
        $payment = new Payment($paymentIntent);

        $this->assertSame($dummyPaymentIntent, $payment->toArray());
    }

    public function test_to_json(): void
    {
        $dummyPaymentIntent = [
            'id' => 'id_123',
            'amount' => 5000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
        ];
        $paymentIntent = PaymentIntent::from($dummyPaymentIntent);
        $payment = new Payment($paymentIntent);

        $this->assertSame(json_encode($dummyPaymentIntent), $payment->toJson());
    }

    public function test_json_serialize(): void
    {
        $dummyPaymentIntent = [
            'id' => 'id_123',
            'amount' => 5000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
        ];
        $paymentIntent = PaymentIntent::from($dummyPaymentIntent);
        $payment = new Payment($paymentIntent);

        $this->assertSame($dummyPaymentIntent, $payment->jsonSerialize());
    }

    public function test_dynamic_property_access(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 5000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
            "currency_code" => 'EUR',
        ]);
        $payment = new Payment($paymentIntent);

        $this->assertSame('EUR', $payment->currency_code);
    }

    public function test_amount(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 5000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
            "currency_code" => 'EUR',
        ]);
        $payment = new Payment($paymentIntent);
        $amount = $payment->amount();

        $this->assertEquals(5000, $payment->rawAmount());
        $this->assertStringContainsString('50.00', $amount);
        $this->assertStringContainsString('€', $amount);
    }

    public function test_requires_action(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 1000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'inited',
        ]);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->requiresAction());
    }

    public function test_requires_capture(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 1000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'authorized',
        ]);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->requiresCapture());
    }

    public function test_is_canceled(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 1000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'expired',
        ]);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isCanceled());
    }

    public function test_is_succeeded(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 1000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
        ]);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isSucceeded());
    }

    public function test_is_processing(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 1000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'in_progress',
        ]);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isProcessing());
    }

    public function test_validate_throws_exception_when_action_required(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 1000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'inited',
        ]);
        $payment = new Payment($paymentIntent);

        $this->expectException(IncompletePayment::class);

        $payment->validate();
    }

    public function test_validate_does_not_throw_exception_when_no_action_required(): void
    {
        $paymentIntent = PaymentIntent::from([
            'id' => 'id_123',
            'amount' => 1000,
            'gateway_account_id' => 'gw_987654321',
            'expires_at' => time() + 3600,
            'created_at' => time(),
            'modified_at' => time(),
            'customer_id' => 'cus_123456',
            'status' => 'consumed',
        ]);
        $payment = new Payment($paymentIntent);

        $payment->validate();

        $this->assertTrue(true);
    }
}
