<?php

namespace Chargebee\Cashier;

use Chargebee\Cashier\Concerns\HandlesTaxes;
use Chargebee\Cashier\Concerns\ManagesCustomer;
use Chargebee\Cashier\Concerns\ManagesInvoices;
use Chargebee\Cashier\Concerns\ManagesPaymentMethods;
use Chargebee\Cashier\Concerns\ManagesSubscriptions;
use Chargebee\Cashier\Concerns\PerformsCharges;

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
