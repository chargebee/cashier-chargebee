<?php

namespace Chargebee\CashierChargebee;

use Chargebee\CashierChargebee\Concerns\HandlesTaxes;
use Chargebee\CashierChargebee\Concerns\ManagesCustomer;
use Chargebee\CashierChargebee\Concerns\ManagesInvoices;
use Chargebee\CashierChargebee\Concerns\ManagesPaymentMethods;
use Chargebee\CashierChargebee\Concerns\ManagesSubscriptions;
use Chargebee\CashierChargebee\Concerns\PerformsCharges;

trait Billable
{
    use HandlesTaxes;
    use ManagesCustomer;
    use ManagesPaymentMethods;
    use ManagesSubscriptions;
    use PerformsCharges;
    use ManagesInvoices;
    use ManagesPaymentMethods;
}
