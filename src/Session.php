<?php

namespace Chargebee\Cashier;

use Chargebee\Resources\HostedPage\HostedPage;

class Session extends HostedPage
{
    const MODE_PAYMENT = 'payment';
    const MODE_SETUP = 'setup';
    const MODE_SUBSCRIPTION = 'subscription';

    protected $mode;

    public function __construct($values, $mode = self::MODE_PAYMENT)
    {
        $hostedPage = HostedPage::from($values);
        foreach (get_object_vars($hostedPage) as $property => $value) {
            $this->$property = $value;
        }
        $this->mode = $mode;
    }
}
