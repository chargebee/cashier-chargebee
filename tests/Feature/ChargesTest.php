<?php

namespace Chargebee\CashierChargebee\Tests\Feature;

use Chargebee\CashierChargebee\Payment;
use Chargebee\CashierChargebee\Tests\Fixtures\User;
use ChargeBee\ChargeBee\Models\PaymentSource;

class ChargesTest extends FeatureTestCase
{
    public function test_customer_can_pay()
    {
        $user = $this->createCustomer('customer_can_pay');
        $user->createAsChargebeeCustomer();

        $response = $user->pay(1000);

        $this->assertInstanceOf(Payment::class, $response);
        $this->assertEquals(1000, $response->rawAmount());
        $this->assertEquals($user->chargebee_id, $response->customerId);

        $payment = $user->findPayment($response->id);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($response->id, $payment->id);
    }

    public function test_customer_can_be_charged_and_invoiced_immediately()
    {
        $user = $this->createCustomer('customer_can_be_charged_and_invoiced_immediately');
        $user->createAsChargebeeCustomer();
        $user->updateDefaultPaymentMethod($this->paymentSource($user));

        $invoice = $user->newInvoice()
            ->tabFor('Laravel Cashier', 1000)
            ->invoice();

        $this->assertEquals('$10.00', $invoice->total());
        $this->assertEquals('Laravel Cashier', $invoice->invoiceItems()[0]->asChargebeeInvoiceLineItem()->description);
    }

    public function test_customer_can_be_refunded()
    {
        $user = $this->createCustomer('customer_can_be_refunded');
        $user->createAsChargebeeCustomer();
        $user->updateDefaultPaymentMethod($this->paymentSource($user));

        $invoice = $user->newInvoice()
            ->tabFor('Laravel Cashier', 1000)
            ->invoice();

        $refund = $user->refund($invoice->id);
        $this->assertEquals(1000, $refund->transaction()->amount);
    }

    protected function paymentSource(User $user)
    {
        return PaymentSource::createCard([
            'customer_id' => $user->chargebeeId(),
            'replace_primary_payment_source' => true,
            'card' => [
                'number' => '4111 1111 1111 1111',
                'cvv' => '123',
                'expiry_month' => now()->month,
                'expiry_year' => now()->addYear()->year,
            ],
        ])->paymentSource();
    }
}
