<?php

declare(strict_types=1);

namespace Chargebee\CashierChargebee\Tests\Unit;

use Chargebee\CashierChargebee\Invoice;
use Chargebee\CashierChargebee\InvoiceLineItem;
use Chargebee\CashierChargebee\Tests\Fixtures\User;
use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;
use ChargeBee\ChargeBee\Models\InvoiceLineItem as ChargeBeeInvoiceLineItem;
use PHPUnit\Framework\TestCase;

class InvoiceLineItemTest extends TestCase
{
    public function test_we_can_calculate_the_inclusive_tax_percentage()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724,
            'priceType' => 'tax_inclusive',
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $chargebeeInvoiceLineItem = new ChargeBeeInvoiceLineItem([
            'taxRate' => 20.0,
        ]);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);
        $result = $item->inclusiveTaxPercentage();
        $this->assertSame(20.0, $result);
    }

    public function test_we_can_calculate_the_exclusive_tax_percentage()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724,
            'priceType' => 'tax_exclusive',
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $chargebeeInvoiceLineItem = new ChargeBeeInvoiceLineItem([
            'taxRate' => 20.0,
        ]);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);
        $result = $item->exclusiveTaxPercentage();
        $this->assertSame(20.0, $result);
    }

    public function test_can_get_period()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => now()->getTimestamp(),
            'priceType' => 'tax_exclusive',
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $chargebeeInvoiceLineItem = new ChargeBeeInvoiceLineItem([
            'taxRate' => 20.0,
            'dateFrom' => now()->addDay()->getTimestamp(),
            'dateTo' => now()->addDays(30)->getTimestamp(),
        ]);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);

        $this->assertNotNull($item->startDate());
        $this->assertNotNull($item->endDate());
    }

    public function test_can_determine_is_subscription()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => now()->getTimestamp(),
            'priceType' => 'tax_exclusive',
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $chargebeeInvoiceLineItem = new ChargeBeeInvoiceLineItem([
            'subscriptionId' => 'foo',
        ]);

        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);

        $this->assertTrue($item->isSubscription());
    }
}
