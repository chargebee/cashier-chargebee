<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\Transaction as ChargebeeTransaction;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

class Transaction implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee Transaction instance.
     *
     * @var \ChargeBee\ChargeBee\Models\Transaction
     */
    protected $transaction;

    /**
     * The related customer instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $customer;

    /**
     * Create a new Payment instance.
     */
    public function __construct(ChargebeeTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Retrieve the related customer for the payment intent if one exists.
     */
    public function customer(): Model|null
    {
        if ($this->customer) {
            return $this->customer;
        }

        return $this->customer = Cashier::findBillable($this->transaction->customerId);
    }

    /**
     * The Chargebee Transaction instance.
     */
    public function asChargebeeTransaction(): ChargebeeTransaction
    {
        return $this->transaction;
    }

    /**
     * Get the total amount that will be paid.
     */
    public function amount(): string
    {
        return Cashier::formatAmount($this->rawAmount(), $this->transaction->currencyCode);
    }

    /**
     * Get the raw total amount that will be paid.
     */
    public function rawAmount(): int
    {
        return $this->transaction->amount;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asChargebeeTransaction()->getValues();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
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
    public function __get($key)
    {
        return $this->transaction->{$key};
    }
}
