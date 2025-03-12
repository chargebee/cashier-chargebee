<?php

namespace Chargebee\Cashier;

use Chargebee\Cashier\Exceptions\IncompletePayment;
use ChargeBee\ChargeBee\Models\PaymentIntent;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

class Payment implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee PaymentIntent instance.
     *
     * @var \ChargeBee\ChargeBee\Models\PaymentIntent
     */
    protected $paymentIntent;

    /**
     * The related customer instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $customer;

    /**
     * Create a new Payment instance.
     */
    public function __construct(PaymentIntent $paymentIntent)
    {
        $this->paymentIntent = $paymentIntent;
    }

    /**
     * Retrieve the related customer for the payment intent if one exists.
     */
    public function customer(): Model|null
    {
        if ($this->customer) {
            return $this->customer;
        }

        return $this->customer = Cashier::findBillable($this->paymentIntent->customerId);
    }

    /**
     * The Chargebee PaymentIntent instance.
     */
    public function asChargebeePaymentIntent(): PaymentIntent
    {
        return $this->paymentIntent;
    }

    /**
     * Get the total amount that will be paid.
     */
    public function amount(): string
    {
        return Cashier::formatAmount($this->rawAmount(), $this->paymentIntent->currencyCode);
    }

    /**
     * Get the raw total amount that will be paid.
     */
    public function rawAmount(): int
    {
        return $this->paymentIntent->amount;
    }

    /**
     * Determine if the payment needs an extra action like 3D Secure.
     */
    public function requiresAction(): bool
    {
        return $this->paymentIntent->status === 'inited';
    }

    /**
     * Determine if the payment needs to be captured.
     */
    public function requiresCapture(): bool
    {
        return $this->paymentIntent->status === 'authorized';
    }

    /**
     * Determine if the payment was canceled.
     */
    public function isCanceled(): bool
    {
        return $this->paymentIntent->status === 'expired';
    }

    /**
     * Determine if the payment was successful.
     */
    public function isSucceeded(): bool
    {
        return $this->paymentIntent->status === 'consumed';
    }

    /**
     * Determine if the payment is processing.
     */
    public function isProcessing(): bool
    {
        return $this->paymentIntent->status === 'in_progress';
    }

    /**
     * Validate if the payment intent was successful and throw an exception if not.
     *
     * @throws \Chargebee\Cashier\Exceptions\IncompletePayment
     */
    public function validate(): void
    {
        if ($this->requiresAction()) {
            throw IncompletePayment::requiresAction($this);
        }
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeePaymentIntent()->getValues();
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
        return $this->paymentIntent->{$key};
    }
}
