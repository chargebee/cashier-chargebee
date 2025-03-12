<?php

namespace Chargebee\CashierChargebee;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Models\Coupon as ChargeBeeCoupon;
use ChargeBee\ChargeBee\Models\Discount as ChargeBeeDiscount;
use ChargeBee\ChargeBee\Models\InvoiceDiscount as ChargeBeeInvoiceDiscount;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class Discount implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee Discount instance.
     *
     * @var ChargeBeeDiscount
     */
    protected $discount;

    /**
     * @var ChargeBeeCoupon
     */
    protected $coupon;

    /**
     * Create a new Discount instance.
     *
     * @param  \ChargeBee\ChargeBee\Models\Discount|\ChargeBee\ChargeBee\Models\InvoiceDiscount  $discount
     * @return void
     */
    public function __construct(ChargeBeeDiscount|ChargeBeeInvoiceDiscount $discount)
    {
        $this->discount = $discount;
    }

    /**
     * Get the coupon applied to the discount.
     *
     * @return \Chargebee\CashierChargebee\Coupon|null
     */
    public function coupon(): Coupon|null
    {
        if (! is_null($this->coupon)) {
            return new Coupon($this->coupon);
        }

        if (
            $this->discount->entityType == 'item_level_coupon' ||
            $this->discount->entityType == 'document_level_coupon'
        ) {
            $this->coupon = ChargeBeeCoupon::retrieve($this->discount->entityId)->coupon();

            return new Coupon($this->coupon);
        }

        return null;
    }

    /**
     * Get the date that the coupon was applied.
     *
     * @return \Carbon\Carbon|null
     */
    public function start()
    {
        if (! is_null($this->discount->start)) {
            return Carbon::createFromTimestamp($this->discount->start);
        }
    }

    /**
     * Get the date that this discount will end.
     *
     * @return \Carbon\Carbon|null
     */
    public function end(): Carbon|null
    {
        if (! is_null($this->discount->end)) {
            return Carbon::createFromTimestamp($this->discount->end);
        }

        return null;
    }

    /**
     * Get the Chargebee Discount instance.
     *
     * @return \ChargeBee\ChargeBee\Models\Discount
     */
    public function asChargebeeDiscount(): ChargeBeeDiscount
    {
        return $this->discount;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeeDiscount()->getValues();
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
        return $this->discount->{$key};
    }
}
