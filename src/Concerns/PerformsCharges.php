<?php

namespace Chargebee\Cashier\Concerns;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Checkout;
use Chargebee\Cashier\Exceptions\PaymentNotFound;
use Chargebee\Cashier\Payment;
use Chargebee\Exceptions\InvalidRequestException;
use Illuminate\Support\Arr;

trait PerformsCharges
{
    use AllowsCoupons;

    /**
     * Create a new PaymentIntent instance.
     */
    public function pay(int $amount, array $options = []): Payment
    {
        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new Payment instance with a Chargebee PaymentIntent.
     */
    public function createPayment(int $amount, array $options = []): Payment
    {
        $options = array_merge([
            'currency_code' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;

        if ($this->hasChargebeeId()) {
            $options['customer_id'] = $this->chargebeeId();
        }
        $chargebee = Cashier::chargebee();
        $result = $chargebee->paymentIntent()->create($options);

        return new Payment(
            $result->payment_intent
        );
    }

    /**
     * Find a payment intent by ID.
     */
    public function findPayment(string $id): Payment
    {
        try {
            $chargebee = Cashier::chargebee();
            $result = $chargebee->paymentIntent()->retrieve($id);

            return new Payment(
                $result->payment_intent
            );
        } catch (InvalidRequestException $exception) {
            if (strpos($exception->getApiErrorCode(), 'resource_not_found') !== false) {
                throw PaymentNotFound::notFound($id);
            }
            throw $exception;
        }
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  string  $invoiceId
     * @param  array  $options
     * @return \Chargebee\Responses\InvoiceResponse\RefundInvoiceResponse
     */
    public function refund($invoiceId, array $options = [])
    {
        $chargeee = Cashier::chargebee();

        return $chargeee->invoice()->refund($invoiceId, $options);
    }

    /**
     * Begin a new checkout session for existing prices.
     *
     * @param  array|string  $items
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Chargebee\Cashier\Checkout
     */
    public function checkout($items, array $sessionOptions = [], array $customerOptions = [])
    {
        return Checkout::customer($this, $this)->create($items, $sessionOptions, $customerOptions);
    }

    /**
     * Begin a new checkout session for a "one-off" charge.
     *
     * @param  int  $amount
     * @param  string  $name
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @param  array  $productData
     * @return \Chargebee\Cashier\Checkout
     */
    public function checkoutCharge($amount, $name, array $sessionOptions = [], array $customerOptions = [], array $productData = [])
    {
        $charges = Arr::get($sessionOptions, 'charges', []);

        $charges[] = [
            array_merge($productData, [
                'amount' => $amount,
                'description' => Arr::get($productData, 'description', $name) ?? $name,
            ]),
        ];

        return $this->checkout([], array_merge($sessionOptions, [
            'currency_code' => $this->preferredCurrency(),
            'charges' => $charges,
        ]), $customerOptions);
    }
}
