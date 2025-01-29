<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\PaymentSource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Illuminate\Database\Eloquent\Model;
use Laravel\CashierChargebee\Exceptions\InvalidPaymentMethod;
use LogicException;

class PaymentMethod
{

    /**
     * @throws InvalidPaymentMethod
     */
    public function __construct( protected Model $owner, protected PaymentSource $paymentMethod)
    {
        if (is_null($paymentMethod->customerId)) {
            throw new LogicException('The payment method is not attached to a customer.');
        }

        if ($owner->chargebee_id !== $paymentMethod->customerId) {
            throw InvalidPaymentMethod::invalidOwner($paymentMethod, $owner);
        }
    }

    /**
     * Delete the payment method.
     */
    public function delete(): void
    {
        $this->owner->deletePaymentMethod($this->paymentMethod);
    }

    /**
     * Get the Chargebee model instance.
     */
    public function owner(): Model
    {
        return $this->owner;
    }

    /**
     * Get the Chargebee PaymentSource instance.
     */
    public function asChargebeePaymentMethod(): PaymentSource
    {
        return $this->paymentMethod;
    }

    /**
     * Dynamically get values from the Chargebee object.
     */
    public function __get(string $key): mixed
    {
        return $this->paymentMethod->{$key};
    }
}
