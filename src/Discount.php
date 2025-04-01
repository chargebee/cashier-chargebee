<?php

namespace Chargebee\Cashier;

use Chargebee\Resources\Coupon\Coupon as ChargeBeeCoupon;
use Chargebee\Resources\Discount\Discount as ChargeBeeDiscount;
use Chargebee\Resources\Invoice\Discount as ChargeBeeInvoiceDiscount;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class Discount implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee Discount instance.
     *
     * @var ChargeBeeDiscount | ChargeBeeInvoiceDiscount
     */
    protected $discount;

    /**
     * @var ChargeBeeCoupon
     */
    protected $coupon;

    /**
     * Create a new Discount instance.
     *
     * @param  ChargeBeeDiscount | ChargeBeeInvoiceDiscount  $discount
     * @return void
     */
    public function __construct(ChargeBeeDiscount | ChargeBeeInvoiceDiscount $discount)
    {
        $this->discount = $discount;
    }

    /**
     * Get the coupon applied to the discount.
     *
     * @return \Chargebee\Cashier\Coupon|null
     */
    public function coupon(): Coupon|null
    {
        $chargebee = Cashier::chargebee();
        if (! is_null($this->coupon)) {
            return new Coupon($this->coupon);
        }

        if (
            $this->discount->entity_type == 'item_level_coupon' ||
            $this->discount->entity_type == 'document_level_coupon'
        ) {
            $this->coupon = $chargebee->coupon()->retrieve($this->discount->entity_id)->coupon;

            return new Coupon($this->coupon);
        }

        return null;
    }
    /**
     * Get the Chargebee Discount instance.
     *
     * @return  ChargeBeeDiscount | ChargeBeeInvoiceDiscount
     */
    public function asChargebeeDiscount(): ChargeBeeDiscount | ChargeBeeInvoiceDiscount
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
        return $this->asChargebeeDiscount()->toArray();
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
