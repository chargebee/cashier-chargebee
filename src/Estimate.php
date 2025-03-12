<?php

namespace Chargebee\CashierChargebee;

use Chargebee\CashierChargebee\Exceptions\InvalidEstimate;
use ChargeBee\ChargeBee\Models\InvoiceEstimate as ChargeBeeEstimate;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

class Estimate implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Chargebee estimate instance.
     *
     * @var ChargeBeeEstimate
     */
    protected $estimate;

    /**
     * Create a new estimate instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \ChargeBee\ChargeBee\Models\InvoiceEstimate  $estimate
     * @return void
     *
     * @throws \Chargebee\CashierChargebee\Exceptions\InvalidInvoice
     */
    public function __construct($owner, ChargeBeeEstimate $estimate)
    {
        if ($owner->chargebee_id !== $estimate->customerId) {
            throw InvalidEstimate::invalidOwner($estimate, $owner);
        }

        $this->owner = $owner;
        $this->estimate = $estimate;
    }

    /**
     * Get the Chargebee model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function owner(): Model
    {
        return $this->owner;
    }

    /**
     * Get the Chargebee estimate instance.
     *
     * @return \ChargeBee\ChargeBee\Models\InvoiceEstimate
     */
    public function asChargebeeEstimate()
    {
        return $this->estimate;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeeEstimate()->getValues();
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
        return $this->estimate->{$key};
    }
}
