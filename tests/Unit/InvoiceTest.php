<?php

namespace Chargebee\CashierChargebee\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use ChargeBee\ChargeBee\Models\Discount as ChargeBeeDiscount;
use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;
use ChargeBee\ChargeBee\Models\InvoiceLineItemTax;
use Chargebee\CashierChargebee\Discount;
use Chargebee\CashierChargebee\Invoice;
use Chargebee\CashierChargebee\Tests\Fixtures\User;
use Chargebee\CashierChargebee\Tests\TestCase;
use Mockery as m;

class InvoiceTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_can_return_the_invoice_date()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724,
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $date = $invoice->date();

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertEquals(1560541724, $date->unix());
    }

    public function test_it_can_return_the_invoice_date_with_a_timezone()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724,
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $date = $invoice->date('CET');

        $this->assertInstanceOf(CarbonTimeZone::class, $timezone = $date->getTimezone());
        $this->assertEquals('CET', $timezone->getName());
    }

    public function test_it_can_return_its_subtotal()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'subTotal' => 500,
            'currencyCode' => config('cashier.currency'),
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $subtotal = $invoice->subtotal();

        $this->assertEquals('$5.00', $subtotal);
    }

    public function test_it_can_determine_if_it_has_a_discount_applied()
    {
        $discountAmount = new ChargeBeeDiscount([
            'entityId' => 'foo',
            'description' => 'foo',
            'amount' => 50,
        ]);

        $otherDiscountAmount = new ChargeBeeDiscount([
            'entityId' => 'bar',
            'description' => 'bar',
            'amount' => 100,
        ]);

        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'discounts' => [$discountAmount, $otherDiscountAmount],
            'total' => 1000,
            'currencyCode' => config('cashier.currency'),
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);
        $this->assertTrue($invoice->hasDiscount());
        $this->assertNotNull($invoice->discounts());
        $this->assertSame('$1.50', $invoice->discount());
        $this->assertSame(50, $invoice->rawDiscountFor(new Discount($discountAmount)));
        $this->assertSame('$0.50', $invoice->discountFor(new Discount($discountAmount)));

        $this->assertNull($invoice->rawDiscountFor(
            new Discount(new ChargeBeeDiscount([
                'entity_id' => 'baz',
                'description' => 'baz',
                'amount' => 100,
            ]))
        ));
    }

    public function test_it_can_return_its_tax()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'tax' => 50,
            'currencyCode' => config('cashier.currency'),
            'total' => 1000,
            'lineItemTaxes' => [
                new InvoiceLineItemTax([
                    'tax_name' => 'GST',
                    'tax_amount' => 50,
                    'tax_rate' => '0.5',
                ]),
            ],
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.50', $invoice->taxes()[0]->amount());
        $this->assertEquals('$0.50', $tax);

        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'currencyCode' => config('cashier.currency'),
            'total' => 1000,
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.00', $tax);
    }
}
