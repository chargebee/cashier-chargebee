<?php

namespace Chargebee\Cashier\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Chargebee\Cashier\Discount;
use Chargebee\Cashier\Invoice;
use Chargebee\Cashier\Tests\Fixtures\User;
use Chargebee\Cashier\Tests\TestCase;
use Chargebee\Resources\Invoice\Discount as ChargeBeeDiscount;
use Chargebee\Resources\Invoice\Invoice as ChargeBeeInvoice;
use Chargebee\Resources\Invoice\LineItemTax;
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
        $dummyInvoiceData = [
            'id' => 'inv_12345',
            'customer_id' => 'foo',
            'recurring' => true,
            'currency_code' => 'EUR',
            'sub_total' => 10000,
            'tax' => 2000,
            'term_finalized' => true,
            'is_gifted' => false,
            'deleted' => false,
            'date' => 1560541724,
            'price_type' => "tax_inclusive",
            'channel' => "web",
            'status' => "paid",
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);
        

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $date = $invoice->date();

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertEquals(1560541724, $date->unix());
    }

    public function test_it_can_return_the_invoice_date_with_a_timezone()
    {
        $dummyInvoiceData = [
            'id' => 'inv_12345',
            'customer_id' => 'foo',
            'recurring' => true,
            'currency_code' => 'EUR',
            'sub_total' => 10000,
            'tax' => 2000,
            'term_finalized' => true,
            'is_gifted' => false,
            'deleted' => false,
            'date' => 1560541724,
            'price_type' => "tax_inclusive",
            'channel' => "web",
            'status' => "paid",
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);
        

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $date = $invoice->date('CET');

        $this->assertInstanceOf(CarbonTimeZone::class, $timezone = $date->getTimezone());
        $this->assertEquals('CET', $timezone->getName());
    }

    public function test_it_can_return_its_subtotal()
    {

        $dummyInvoiceData = [
            'id' => 'inv_12345',
            'customer_id' => 'foo',
            'recurring' => true,
            'currency_code' => config('cashier.currency'),
            'sub_total' => 500,
            'tax' => 2000,
            'term_finalized' => true,
            'is_gifted' => false,
            'deleted' => false,
            'date' => 1560541724,
            'price_type' => "tax_inclusive",
            'channel' => "web",
            'status' => "paid",
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);
        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $subtotal = $invoice->subtotal();

        $this->assertEquals('$5.00', $subtotal);
    }

    public function test_it_can_determine_if_it_has_a_discount_applied()
    {
        $dummyDiscountData = [
            "amount" => 50, 
            "entity_type" => "ent_id",
            "entity_id" => "foo",
            "description" => "foo"
         ];  
         $discountAmount = ChargebeeDiscount::from($dummyDiscountData);
         $otherDummyDiscountData = [
            "amount" => 100, 
            "entity_type" => "ent_id",
            "entity_id" => "foo",
            "description" => "foo"
         ];  
         $otherDiscountAmount = ChargebeeDiscount::from($otherDummyDiscountData);
         $dummyInvoiceData = [
            'id' => 'inv_12345',
            'customer_id' => 'foo',
            'recurring' => true,
            'currency_code' => config('cashier.currency'),
            'sub_total' => 10000,
            'tax' => 2000,
            'term_finalized' => true,
            'is_gifted' => false,
            'deleted' => false,
            'date' => 1560541724,
            'price_type' => "tax_exclusive",
            'channel' => "web",
            'status' => "paid",
            'discounts' => [$dummyDiscountData, $otherDummyDiscountData],
            'total' => 1000,
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);
        $this->assertTrue($invoice->hasDiscount());
        $this->assertNotNull($invoice->discounts());
        $this->assertSame('$1.50', $invoice->discount());
        $this->assertSame(50, $invoice->rawDiscountFor(new Discount($discountAmount)));
        $this->assertSame('$0.50', $invoice->discountFor(new Discount($discountAmount)));

        $this->assertNull($invoice->rawDiscountFor(
            new Discount( ChargeBeeDiscount::from([
                'entity_id' => 'baz',
                'description' => 'baz',
                'amount' => 100,
                'entity_type' => 'baz'
            ]))
        ));
    }

    public function test_it_can_return_its_tax()
    {
        $dummyInvoiceData = [
            'id' => 'inv_12345',
            'customer_id' => 'foo',
            'recurring' => true,
            'currency_code' => config('cashier.currency'),
            'sub_total' => 500,
            'tax' => 50,
            'term_finalized' => true,
            'is_gifted' => false,
            'deleted' => false,
            'date' => 1560541724,
            'price_type' => "tax_exclusive",
            'channel' => "web",
            'status' => "paid",
            'total' => 1000,
            'line_item_taxes' => [ 
                [
                'tax_name' => 'GST',
                'tax_amount' => 50,
                'tax_rate' => '0.5',
                'taxable_amount' => 1000,
                'line_item_id' => 'foo'
                ]
            ]
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $tax = $invoice->tax();
        
        $this->assertEquals('$0.50', $invoice->taxes()[0]->amount());
        $this->assertEquals('$0.50', $tax);

        $secondDummyInvoiceData = [
            'id' => 'inv_12345',
            'customer_id' => 'foo',
            'recurring' => true,
            'currency_code' => config('cashier.currency'),
            'sub_total' => 10000,
            'tax' => 0,
            'term_finalized' => true,
            'is_gifted' => false,
            'deleted' => false,
            'date' => 1560541724,
            'price_type' => "tax_exclusive",
            'channel' => "web",
            'status' => "paid",
            'total' => 1000
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($secondDummyInvoiceData);
        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.00', $tax);
    }
}
