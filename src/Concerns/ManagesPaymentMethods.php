<?php

namespace Chargebee\Cashier\Concerns;

use Chargebee\Cashier\Cashier;
use Chargebee\Cashier\Exceptions\CustomerNotFound;
use Chargebee\Cashier\Exceptions\InvalidPaymentMethod;
use Chargebee\Cashier\PaymentMethod;
use Chargebee\Exceptions\InvalidRequestException;
use Chargebee\Resources\Customer\Customer;
use Chargebee\Resources\PaymentIntent\PaymentIntent;
use Chargebee\Resources\PaymentSource\PaymentSource;
use Illuminate\Support\Collection;

trait ManagesPaymentMethods
{
    /**
     * Create a new PaymentIntent instance with amount = 0.
     *
     * @throws CustomerNotFound
     */
    public function createSetupIntent(array $options = []): ?PaymentIntent
    {
        $this->assertCustomerExists();

        $defaultOptions = [
            'customer_id' => $this->chargebeeId(),
            'amount' => 0,
            'currency_code' => ! empty($options['currency_code'])
                ? $options['currency_code']
                : config('cashier.currency'),
        ];
        $chargebee = Cashier::chargebee();
        $paymentIntent = $chargebee->paymentIntent()->create(array_merge($options, $defaultOptions));

        return $paymentIntent?->payment_intent;
    }

    /**
     * Retrieve a PaymentIntent from ChargeBee.
     */
    public function findSetupIntent(string $id): ?PaymentIntent
    {
        $chargebee = Cashier::chargebee();
        $paymentIntent = $chargebee->paymentIntent()->retrieve($id);

        return $paymentIntent?->payment_intent;
    }

    /**
     * Determines if the customer currently has at least one payment method of an optional type.
     */
    public function hasPaymentMethod(?string $type = null): bool
    {
        return $this->paymentMethods($type)->isNotEmpty();
    }

    /**
     * Get a collection of the customer's payment methods of an optional type.
     */
    public function paymentMethods(?string $type = null, array $parameters = []): ?Collection
    {
        if (! $this->hasChargebeeId()) {
            return new Collection();
        }

        $parameters = array_merge(['limit' => 24], $parameters);
        $chargebee = Cashier::chargebee();
        $paymentSources = $chargebee->paymentSource()->all(
            array_filter(['customer_id[is]' => $this->chargebeeId(), 'type[is]' => $type]) + $parameters
        );

        return Collection::make($paymentSources->list)->map(function ($paymentSource) {
            return $paymentSource->payment_source;
        });
    }

    /**
     * Add a payment method to the customer.
     *
     * @throws InvalidPaymentMethod
     * @throws CustomerNotFound
     * @throws InvalidRequestException
     */
    public function addPaymentMethod(PaymentSource $paymentSource, bool $setAsDefault = false): PaymentMethod
    {
        $this->assertCustomerExists();

        if ($setAsDefault) {
            $this->updateDefaultPaymentMethod($paymentSource);
        }

        return new PaymentMethod($this, $paymentSource);
    }

    /**
     * Delete a payment method to the customer.
     *
     * @throws CustomerNotFound
     * @throws InvalidRequestException
     */
    public function deletePaymentMethod(PaymentSource $paymentSource): void
    {
        $this->assertCustomerExists();

        if ($this->chargebeeId() !== $paymentSource->customer_id) {
            throw InvalidPaymentMethod::invalidOwner($paymentSource, $this);
        }

        $customer = $this->asChargebeeCustomer();

        if (! empty($customer->primaryPaymentSourceId) && $paymentSource->id === $customer->primaryPaymentSourceId) {
            $this->forceFill([
                'pm_type' => null,
                'pm_last_four' => null,
            ])->save();
        }
        $chargebee = Cashier::chargebee();
        $chargebee->paymentSource()->delete($paymentSource->id);
    }

    /**
     * Delete a payment method to the customer.
     *
     * @throws CustomerNotFound
     * @throws InvalidRequestException
     */
    public function deletePaymentMethods(string $type): void
    {
        $this->paymentMethods($type)->each(function (PaymentSource $paymentSource) {
            $this->deletePaymentMethod($paymentSource);
        });
    }

    /**
     * Get the default payment method for the customer.
     *
     * @throws CustomerNotFound
     * @throws InvalidRequestException
     * @throws InvalidPaymentMethod
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        $this->assertCustomerExists();

        $customer = $this->asChargebeeCustomer();

        if (! empty($customer->primary_payment_source_id)) {
            return new PaymentMethod($this, $this->resolveChargebeePaymentMethod($customer->primary_payment_source_id));
        }

        return null;
    }

    /**
     * Synchronises the customer's default payment method from Chargebee back into the database.
     *
     * @throws InvalidPaymentMethod
     * @throws InvalidRequestException
     * @throws CustomerNotFound
     */
    public function updateDefaultPaymentMethodFromChargebee(): self
    {
        $defaultPaymentMethod = $this->defaultPaymentMethod();
        if ($defaultPaymentMethod && $defaultPaymentMethod instanceof PaymentMethod) {
            $this->fillPaymentMethodDetails(
                $defaultPaymentMethod->asChargebeePaymentMethod()
            )->save();
        } else {
            $this->forceFill([
                'pm_type' => null,
                'pm_last_four' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Set Default PaymentMethod.
     *
     * @throws InvalidPaymentMethod
     * @throws InvalidRequestException
     * @throws CustomerNotFound
     */
    public function updateDefaultPaymentMethod(PaymentSource|string $paymentSource): ?Customer
    {
        $this->assertCustomerExists();

        $paymentSource = $this->resolveChargebeePaymentMethod($paymentSource);
        $chargebee = Cashier::chargebee();

        if ($paymentSource) {
            $chargebee->customer()->assignPaymentRole(
                $this->chargebeeId(),
                [
                    'payment_source_id' => $paymentSource->id,
                    'role' => 'PRIMARY',
                ]
            );

            $this->fillPaymentMethodDetails($paymentSource)->save();
        }

        return null;
    }

    /**
     * Fills the model's properties with the payment method from Chargebee.
     */
    protected function fillPaymentMethodDetails(PaymentSource $paymentSource): self
    {
        if ($paymentSource->type->value === 'card') {
            $this->pm_type = $paymentSource->card->brand;
            $this->pm_last_four = $paymentSource->card->last4;
        } else {
            $this->pm_type = $type = $paymentSource->type->value;
            $this->pm_last_four = $paymentSource?->$type->last4 ?? null;
        }

        return $this;
    }

    /**
     * Find a PaymentMethod by ID.
     *
     * @throws InvalidPaymentMethod
     * @throws InvalidRequestException
     */
    public function findPaymentMethod(PaymentSource|string $paymentSource): ?PaymentMethod
    {
        $paymentSource = $this->resolveChargebeePaymentMethod($paymentSource);

        return $paymentSource
            ? new PaymentMethod($this, $paymentSource)
            : null;
    }

    /**
     * Resolve a PaymentMethod as a Chargebee PaymentSource object.
     *
     * @throws InvalidRequestException
     * @throws InvalidPaymentMethod
     */
    protected function resolveChargebeePaymentMethod(PaymentSource|string $paymentSource): ?PaymentSource
    {
        if ($paymentSource instanceof PaymentSource) {
            return $paymentSource;
        }
        $chargebee = Cashier::chargebee();
        return $chargebee->paymentSource()->retrieve($paymentSource)?->payment_source;
    }
}
