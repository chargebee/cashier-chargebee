<?php

namespace Chargebee\Cashier;

use Chargebee\Resources\Coupon\Coupon as ChargeBeeCoupon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class Coupon implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee Coupon instance.
     *
     * @var \Chargebee\Resources\Coupon\Coupon
     */
    protected $coupon;

    /**
     * Create a new Coupon instance.
     *
     * @param  \Chargebee\Resources\Coupon\Coupon $coupon
     * @return void
     */
    public function __construct(ChargeBeeCoupon $coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * Get the readable name for the Coupon.
     *
     * @return string
     */
    public function name(): mixed
    {
        return $this->coupon->name ?: $this->coupon->id;
    }

    /**
     * Determine if the coupon is a percentage.
     *
     * @return bool
     */
    public function isPercentage(): bool
    {
        return $this->coupon->discount_type == 'percentage';
    }

    /**
     * Get the discount percentage for the invoice.
     *
     * @return float|null
     */
    public function percentOff(): mixed
    {
        return $this->coupon->discount_percentage;
    }

    /**
     * Get the amount off for the coupon.
     *
     * @return string|null
     */
    public function amountOff(): string|null
    {
        if (! is_null($this->coupon->discount_amount)) {
            return $this->formatAmount($this->rawAmountOff());
        }

        return null;
    }

    /**
     * Get the raw amount off for the coupon.
     *
     * @return int|null
     */
    public function rawAmountOff(): mixed
    {
        return $this->coupon->discount_amount;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->coupon->currency_code);
    }

    /**
     * Get the Chargebee Coupon instance.
     *
     * @return \Chargebee\Resources\Coupon\Coupon
     */
    public function asChargebeeCoupon(): ChargeBeeCoupon
    {
        return $this->coupon;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeeCoupon()->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): bool|string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Chargebee object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        return $this->coupon->{$key};
    }
}
