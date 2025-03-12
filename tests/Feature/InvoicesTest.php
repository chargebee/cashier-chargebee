<?php

namespace Chargebee\Cashier\Tests\Feature;

use Chargebee\Cashier\Exceptions\CustomerNotFound;
use Chargebee\Cashier\Exceptions\InvalidInvoice;
use Chargebee\Cashier\Invoice;
use Chargebee\Cashier\Tests\Fixtures\User;
use ChargeBee\ChargeBee\Models\Coupon;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\PaymentSource;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoicesTest extends FeatureTestCase
{
    public function test_require_customer_for_invoicing()
    {
        $user = $this->createCustomer('require_customer_for_invoicing');
        $this->expectException(CustomerNotFound::class);
        $user->newInvoice()->invoice();
    }

    public function test_customer_can_be_invoiced()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced');
        $invoice = $user->invoiceFor('Laracon', 49900);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(49900, $invoice->total);
    }

    public function test_customer_can_be_invoiced_with_a_coupon()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced_with_coupon');

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

        $invoice = $user->invoiceFor('Laracon', 49900, [], [
            'couponIds' => [$coupon->id],
        ]);

        $this->assertNotNull($invoice->discounts()[0]->coupon());
        $this->assertEquals($id, $invoice->discounts()[0]->coupon()->id);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(49400, $invoice->total);
    }

    public function test_customer_can_be_invoiced_with_a_price()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced');
        $price = $this->createPrice('Laravel T-shirt', amount: 499);

        $invoice = $user->invoicePrice($price->id, 2);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(998, $invoice->total);
    }

    public function test_customer_can_be_invoiced_with_inline_price_data()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced_with_inline_price_data');

        $response = $user->invoiceFor('Laravel T-shirt', 599, ['taxable' => false]);

        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertEquals(599, $response->total);
        $this->assertEquals(false, $response->invoiceLineItems()[0]->isTaxed);
    }

    public function test_find_invoice_by_id()
    {
        $user = $this->createCustomerWithPaymentSource('find_invoice_by_id');
        $invoice = $user->invoiceFor('Laravel T-shirt', 49900);

        $invoice = $user->findInvoice($invoice->id);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(49900, $invoice->rawTotal());
    }

    public function test_find_invoice_returns_null()
    {
        $user = $this->createCustomerWithPaymentSource('find_invoice_by_id');

        $invoice = $user->findInvoice('TEST');
        $this->assertNull($invoice);
    }

    public function test_it_throws_an_exception_if_the_invoice_does_not_belong_to_the_user()
    {
        $user = $this->createCustomerWithPaymentSource('throws_exception_invoice_doesnt_belong_to_user');

        $otherUser = $this->createCustomer('other_user');
        $invoice = $user->invoiceFor('Laravel T-shirt', 49900);

        $this->expectException(InvalidInvoice::class);
        $this->expectExceptionMessage(
            "The invoice `{$invoice->id}` does not belong to this customer `$otherUser->chargebee_id`."
        );

        $otherUser->findInvoice($invoice->id);
    }

    public function test_find_invoice_by_id_or_fail()
    {
        $user = $this->createCustomerWithPaymentSource('find_invoice_by_id_or_fail');
        $otherUser = $this->createCustomerWithPaymentSource('other_user');
        $invoice = $user->invoiceFor('Laracon', 49900);

        $this->expectException(AccessDeniedHttpException::class);
        $otherUser->findInvoiceOrFail($invoice->id);
    }

    public function test_find_invoice_by_id_or_fail_no_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('find_invoice_by_id_or_fail_no_invoice');

        $this->expectException(NotFoundHttpException::class);
        $user->findInvoiceOrFail('test');
    }

    public function test_customer_can_be_invoiced_with_quantity()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced_with_quantity');

        $response = $user->invoiceFor('Laracon', amount: 5000);

        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertEquals(5000, $response->total);
    }

    public function test_upcoming_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('subscription_upcoming_invoice');
        $price = $this->createSubscriptionPrice('Laracon', 1000);
        $subscription = $user->newSubscription('main', $price->id)
            ->create();

        $estimate = $user->upcomingInvoice(['subscription' => $subscription->chargebee_id]);
        $this->assertSame(1000, $estimate->total);
    }

    public function test_it_returns_null_when_no_chargebee_user()
    {
        $user = $this->createCustomer('upcoming_invoice');

        $invoice = $user->upcomingInvoice();
        $this->assertNull($invoice);

        $user->chargebee_id = 'foo';
        $invoice = $user->upcomingInvoice();
        $this->assertNull($invoice);
    }

    public function test_download_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('downloading_invoice');
        $response = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice();

        $data = $user->downloadInvoice($response->id);
        $this->assertNotNull($data);
    }

    public function test_get_invoices()
    {
        $user = $this->createCustomerWithPaymentSource('getting_invoices');

        for ($i = 0; $i < 25; $i++) {
            $response = $user->newInvoice()
                ->tabFor("Laracon-$i", amount: 5000)
                ->invoice();
        }

        $paginatedInvoices = $user->cursorPaginateInvoices(2, [], 'cursor', null);
        $this->assertCount(2, $paginatedInvoices->items());
        $this->assertNotNull($paginatedInvoices->nextCursor());

        $currentPage = $paginatedInvoices;
        $totalInvoicesFetched = 2;

        while ($currentPage->nextCursor()) {
            $currentPage = $user->cursorPaginateInvoices(2, [], 'cursor', $currentPage->nextCursor());
            $totalInvoicesFetched += count($currentPage->items());
        }

        $this->assertEquals(25, $totalInvoicesFetched);

        $this->assertCount(1, $currentPage->items());
        $this->assertNull($currentPage->nextCursor());
    }

    public function test_get_invoices_returns_empty()
    {
        $user = $this->createCustomer('getting_invoices');
        $invoices = $user->invoices();
        $this->assertEmpty($invoices);
    }

    public function test_can_void_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('voiding_invoice');
        $response = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice([
                'autoCollection' => 'off',
            ]);

        $invoice = $response->void();

        $this->assertTrue($invoice->isVoid());
    }

    public function test_can_pay_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('paying_invoice');
        $response = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice([
                'autoCollection' => 'off',
            ]);

        $this->assertFalse($response->isPaid());

        $invoice = $response->pay([
            'off_session' => false,
        ]);

        $this->assertTrue($invoice->isPaid());
    }

    public function test_can_pay_off_session_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('paying_invoice');
        $response = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice([
                'autoCollection' => 'off',
            ]);

        $this->assertFalse($response->isPaid());

        $invoice = $response->pay();

        $this->assertTrue($invoice->isPaid());
    }

    public function test_can_delete_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('deleting_invoice');
        $response = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice([
                'autoCollection' => 'off',
            ]);

        $this->assertFalse($response->deleted);
        $invoice = $response->delete();

        $this->assertNull($user->findInvoice($invoice->id));
    }

    public function test_can_add_price_to_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('add_price_to_invoice');
        $price = $this->createSubscriptionPrice('Laracon', amount: 5000);

        $subscription = $user->newSubscription('default', $price->id)->create(
            null,
            [],
            [
                'createPendingInvoices' => true,
                'autoCollection' => 'off',
                'firstInvoicePending' => true,
            ]
        );

        $invoice = $user->invoicesIncludingPending()->first();
        $this->assertTrue($invoice->isPending());
        $price = $this->createPrice('Laravel T-shirt', amount: 499);
        $invoice = $invoice->tabPrice($price->id);
        $this->assertEquals(5499, $invoice->total);
    }

    public function test_can_add_charge_to_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('add_charge_to_invoice');
        $price = $this->createSubscriptionPrice('Laracon', amount: 5000);

        $subscription = $user->newSubscription('default', $price->id)->create(
            null,
            [],
            [
                'createPendingInvoices' => true,
                'autoCollection' => 'off',
                'firstInvoicePending' => true,
            ]
        );

        $invoice = $user->invoicesIncludingPending()->first();
        $invoice = $invoice->tab('Laravel T-shirt', 499);
        $this->assertEquals(5499, $invoice->total);
    }

    public function test_it_can_determine_if_sends_invoice()
    {
        $user = $this->createCustomerWithPaymentSource('sends_invoice');
        $invoice = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice([
                'autoCollection' => 'off',
            ]);

        $this->assertFalse($invoice->chargesAutomatically());
        $this->assertTrue($invoice->sendsInvoice());
    }

    public function test_it_can_determine_if_charges_automatically()
    {
        $user = $this->createCustomerWithPaymentSource('sends_invoice');
        $invoice = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice([
                'autoCollection' => 'on',
            ]);

        $this->assertTrue($invoice->chargesAutomatically());
        $this->assertFalse($invoice->sendsInvoice());
    }

    public function test_it_can_determine_if_the_customer_was_exempt_from_taxes()
    {
        $user = $this->createCustomerWithPaymentSource('paying_invoice');
        $invoice = $user->newInvoice()
            ->tabFor('Laracon', amount: 5000)
            ->invoice([
                'autoCollection' => 'off',
            ]);

        $this->assertTrue($invoice->isNotTaxExempt());
        $this->assertFalse($invoice->isTaxExempt());
    }

    protected function createCustomerWithPaymentSource($description = 'testuser', array $options = []): User
    {
        $user = $this->createCustomer($description, $options);
        $user->createAsChargebeeCustomer();

        $paymentSource = PaymentSource::createCard([
            'customer_id' => $user->chargebeeId(),
            'replace_primary_payment_source' => true,
            'card' => [
                'number' => '4111 1111 1111 1111',
                'cvv' => '123',
                'expiry_month' => now()->month,
                'expiry_year' => now()->addYear()->year,
            ],
        ])->paymentSource();

        Customer::assignPaymentRole(
            $user->chargebeeId(),
            [
                'payment_source_id' => $paymentSource->id,
                'role' => 'PRIMARY',
            ]
        );

        return $user;
    }
}
