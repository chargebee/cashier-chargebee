<?php

namespace Chargebee\CashierChargebee\Concerns;

trait AllowsCoupons
{
    /**
     * The coupon IDs being applied.
     *
     * @var array
     */
    public $couponIds = [];

    /**
     * The coupon IDs to be applied.
     *
     * @param  array  $couponIds
     * @return $this
     */
    public function withCoupons($couponIds): self
    {
        $this->couponIds = $couponIds;

        return $this;
    }

    /**
     * Return the discounts for a Chargebee Checkout session.
     *
     * @return array[]|null
     */
    protected function checkoutDiscounts()
    {
        if (count($this->couponIds)) {
            return $this->couponIds;
        }
    }
}
