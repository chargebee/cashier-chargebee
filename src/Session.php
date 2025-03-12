<?php

namespace Chargebee\CashierChargebee;

use ChargeBee\ChargeBee\Models\HostedPage;

class Session extends HostedPage
{
    const MODE_PAYMENT = 'payment';
    const MODE_SETUP = 'setup';
    const MODE_SUBSCRIPTION = 'subscription';

    protected $mode;

    public function __construct($values, $mode = self::MODE_PAYMENT)
    {
        parent::__construct($values);
        $this->mode = $mode;
    }
}
