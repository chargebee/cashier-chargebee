<?php

declare(strict_types=1);

namespace Chargebee\Cashier\Tests\Unit;

use Chargebee\Cashier\Invoice;
use Chargebee\Cashier\InvoiceLineItem;
use Chargebee\Cashier\Tests\Fixtures\User;
use Chargebee\Resources\Invoice\Invoice as ChargeBeeInvoice;
use Chargebee\Resources\Invoice\LineItem as ChargebeeInvoiceLineItem;
use PHPUnit\Framework\TestCase;

class InvoiceLineItemTest extends TestCase
{
    public function test_we_can_calculate_the_inclusive_tax_percentage()
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
        
        $chargebeeInvoice = ChargebeeInvoice::from($dummyInvoiceData);
        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $dummyLineItemData = [
            "date_from" => time(),
            "date_to" => time() + 86400,
            "unit_amount" => 1000,
            "is_taxed" => false,
            "description" => "Sample Line Item",
            "entity_type" => "plan",
            "tax_rate" => 20.0
        ];
        $chargebeeInvoiceLineItem = ChargeBeeInvoiceLineItem::from($dummyLineItemData);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);
        $result = $item->inclusiveTaxPercentage();
        $this->assertSame(20, $result);
    }

    public function test_we_can_calculate_the_exclusive_tax_percentage()
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
            'price_type' => "tax_exclusive",
            'channel' => "web",
            'status' => "paid",
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);
        $dummyLineItemData = [
            "date_from" => time(),
            "date_to" => time() + 86400,
            "unit_amount" => 1000,
            "is_taxed" => false,
            "description" => "Sample Line Item",
            "entity_type" => "plan",
            "tax_rate" => 20.0
        ];

        $chargebeeInvoiceLineItem = ChargeBeeInvoiceLineItem::from($dummyLineItemData);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);
        $result = $item->exclusiveTaxPercentage();
        $this->assertSame(20, $result);
    }

    public function test_can_get_period()
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
            'date' => now()->getTimestamp(),
            'price_type' => "tax_exclusive",
            'channel' => "web",
            'status' => "paid"
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);
        $dummyLineItemData = [
            "date_from" => now()->addDay()->getTimestamp(),
            "date_to" => now()->addDays(30)->getTimestamp(),
            "unit_amount" => 1000,
            "is_taxed" => false,
            "description" => "Sample Line Item",
            "entity_type" => "plan",
            "tax_rate" => 20.0
        ];

        $chargebeeInvoiceLineItem = ChargeBeeInvoiceLineItem::from($dummyLineItemData);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);

        $this->assertNotNull($item->startDate());
        $this->assertNotNull($item->endDate());
    }

    public function test_can_determine_is_subscription()
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
            'date' => now()->getTimestamp(),
            'price_type' => "tax_exclusive",
            'channel' => "web",
            'status' => "paid",
        ];
        $chargebeeInvoice = ChargeBeeInvoice::from($dummyInvoiceData);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);
        $dummyLineItemData = [
            "date_from" => now()->addDay()->getTimestamp(),
            "date_to" => now()->addDays(30)->getTimestamp(),
            "unit_amount" => 1000,
            "is_taxed" => false,
            "description" => "Sample Line Item",
            "entity_type" => "plan",
            "tax_rate" => 20.0,
            "subscription_id" => 'foo'
        ];

        $chargebeeInvoiceLineItem = ChargeBeeInvoiceLineItem::from($dummyLineItemData);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);

        $this->assertTrue($item->isSubscription());
    }
}
