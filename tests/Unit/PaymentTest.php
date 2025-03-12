<?php

namespace Chargebee\Cashier\Tests\Unit;

use Chargebee\Cashier\Exceptions\IncompletePayment;
use Chargebee\Cashier\Payment;
use Chargebee\Cashier\Tests\TestCase;
use ChargeBee\ChargeBee\Models\PaymentIntent;

class PaymentTest extends TestCase
{
    public function test_as_chargebee_payment_intent(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame($paymentIntent, $payment->asChargebeePaymentIntent());
    }

    public function test_to_array(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame(['id' => 'id_123'], $payment->toArray());
    }

    public function test_to_json(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame(json_encode(['id' => 'id_123']), $payment->toJson());
    }

    public function test_json_serialize(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame(['id' => 'id_123'], $payment->jsonSerialize());
    }

    public function test_dynamic_property_access(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123', 'currencyCode' => 'EUR']);
        $payment = new Payment($paymentIntent);

        $this->assertSame('EUR', $payment->currencyCode);
    }

    public function test_amount(): void
    {
        $paymentIntent = new PaymentIntent([
            'id' => 'id_123',
            'currencyCode' => 'EUR',
            'amount' => 5000,
        ]);
        $payment = new Payment($paymentIntent);
        $amount = $payment->amount();

        $this->assertEquals(5000, $payment->rawAmount());
        $this->assertStringContainsString('50.00', $amount);
        $this->assertStringContainsString('€', $amount);
    }

    public function test_requires_action(): void
    {
        $paymentIntent = new PaymentIntent(['status' => 'inited']);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->requiresAction());
    }

    public function test_requires_capture(): void
    {
        $paymentIntent = new PaymentIntent(['status' => 'authorized']);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->requiresCapture());
    }

    public function test_is_canceled(): void
    {
        $paymentIntent = new PaymentIntent(['status' => 'expired']);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isCanceled());
    }

    public function test_is_succeeded(): void
    {
        $paymentIntent = new PaymentIntent(['status' => 'consumed']);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isSucceeded());
    }

    public function test_is_processing(): void
    {
        $paymentIntent = new PaymentIntent(['status' => 'in_progress']);
        $payment = new Payment($paymentIntent);

        $this->assertTrue($payment->isProcessing());
    }

    public function test_validate_throws_exception_when_action_required(): void
    {
        $paymentIntent = new PaymentIntent(['status' => 'inited']);
        $payment = new Payment($paymentIntent);

        $this->expectException(IncompletePayment::class);

        $payment->validate();
    }

    public function test_validate_does_not_throw_exception_when_no_action_required(): void
    {
        $paymentIntent = new PaymentIntent(['status' => 'consumed']);
        $payment = new Payment($paymentIntent);

        $payment->validate();

        $this->assertTrue(true);
    }
}
