<?php

namespace Chargebee\Cashier;

use Chargebee\Cashier\Concerns\AllowsCoupons;
use Chargebee\Cashier\Concerns\HandlesTaxes;
use Illuminate\Support\Collection;

class CheckoutBuilder
{
    use AllowsCoupons;
    use HandlesTaxes;

    /**
     * The Chargebee model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $owner;

    /**
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  object|null  $parentInstance
     * @return void
     */
    public function __construct($owner = null, $parentInstance = null)
    {
        $this->owner = $owner;

        if ($parentInstance && in_array(AllowsCoupons::class, class_uses_recursive($parentInstance))) {
            $this->couponIds = $parentInstance->couponIds;
        }

        if ($parentInstance && in_array(HandlesTaxes::class, class_uses_recursive($parentInstance))) {
            $this->customerIpAddress = $parentInstance->customerIpAddress;
            $this->estimationBillingAddress = $parentInstance->estimationBillingAddress;
            $this->collectTaxIds = $parentInstance->collectTaxIds;
        }
    }

    /**
     * Create a new checkout builder instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  object|null  $instance
     * @return \Chargebee\Cashier\CheckoutBuilder
     */
    public static function make($owner = null, $instance = null): CheckoutBuilder
    {
        return new CheckoutBuilder($owner, $instance);
    }

    /**
     * Create a new checkout session.
     *
     * @param  array|string  $items
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Chargebee\Cashier\Checkout
     */
    public function create($items, array $sessionOptions = [], array $customerOptions = []): Checkout
    {
        $data = array_merge([
            'mode' => Session::MODE_PAYMENT,
        ], $sessionOptions);

        $payload = array_filter([
            'mode' => Session::MODE_PAYMENT,
            'coupon_ids' => $this->checkoutDiscounts(),
            'itemPrices' => Collection::make((array) $items)->map(function ($item, $key) {
                if (is_string($key)) {
                    return ['itemPriceId' => $key, 'quantity' => $item];
                }

                $item = is_string($item) ? ['itemPriceId' => $item] : $item;

                $item['quantity'] = $item['quantity'] ?? 1;

                return $item;
            })->values()->all(),
        ]);

        return Checkout::create($this->owner, array_merge($payload, $data), $customerOptions);
    }
}
