<?php

namespace Chargebee\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\PortalSession;
use ChargeBee\ChargeBee\Models\PromotionalCredit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Chargebee\CashierChargebee\Cashier;
use Chargebee\CashierChargebee\CustomerBalanceTransaction;
use Chargebee\CashierChargebee\Exceptions\CustomerAlreadyCreated;
use Chargebee\CashierChargebee\Exceptions\CustomerNotFound;

trait ManagesCustomer
{
    /**
     * Retrieve the Chargebee customer ID.
     */
    public function chargebeeId(): string|null
    {
        return $this->chargebee_id;
    }

    /**
     * Determine if the customer has a Chargebee customer ID.
     */
    public function hasChargebeeId(): bool
    {
        return ! is_null($this->chargebee_id);
    }

    /**
     * Determine if the customer has a Chargebee customer ID and throw an exception if not.
     *
     * @throws \Chargebee\CashierChargebee\Exceptions\CustomerNotFound
     */
    protected function assertCustomerExists()
    {
        if (! $this->hasChargebeeId()) {
            throw CustomerNotFound::notFound($this);
        }
    }

    /**
     * Create a Chargebee customer for the given model.
     *
     * @throws \Chargebee\CashierChargebee\Exceptions\CustomerAlreadyCreated
     */
    public function createAsChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        $defaultOptions = [
            'firstName' => $this->chargebeeFirstName(),
            'lastName' => $this->chargebeeLastName(),
            'email' => $this->chargebeeEmail(),
            'phone' => $this->chargebeePhone(),
            'billingAddress' => $this->chargebeeBillingAddress(),
            'locale' => $this->chargebeeLocale(),
            'metaData' => $this->chargebeeMetaData(),
        ];

        $options = array_merge(array_filter($defaultOptions), $options);

        // Create a customer instance on Chargebee and store its ID for future retrieval.
        $result = Customer::create($options);
        $customer = $result->customer();

        $this->chargebee_id = $customer->id;
        $this->save();

        return $customer;
    }

    /**
     * Get the Chargebee customer for the model.
     */
    public function asChargebeeCustomer(): Customer
    {
        $this->assertCustomerExists();

        try {
            $response = Customer::retrieve($this->chargebeeId());

            return $response->customer();
        } catch (InvalidRequestException $exception) {
            if (strpos($exception->getApiErrorCode(), 'resource_not_found') !== false) {
                throw CustomerNotFound::notFound($this);
            }
            throw $exception;
        }
    }

    /**
     * Update Chargebee customer information for the model.
     */
    public function updateChargebeeCustomer(array $options = []): Customer
    {
        $this->assertCustomerExists();

        try {
            // We need to make 2 separate API calls to update customer and billing info.
            $response = Customer::update($this->chargebeeId(), $options);

            // Call updateBillingInfo only if billingAddress is not empty and contains at least one non-null, non-empty value.
            if (! empty($options['billingAddress']) && collect($options['billingAddress'])->reject(fn ($value) => is_null($value) || $value === '')->isNotEmpty()) {
                $response = Customer::updateBillingInfo($this->chargebeeId(), $options);
            }

            return $response->customer();
        } catch (InvalidRequestException $exception) {
            if (strpos($exception->getApiErrorCode(), 'resource_not_found') !== false) {
                throw CustomerNotFound::notFound($this);
            }
            throw $exception;
        }
    }

    /**
     * Update customer with data from Chargebee.
     */
    public function updateCustomerFromChargebee(): void
    {
        $customer = $this->asChargebeeCustomer();

        $chargebeeData = [
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];

        $table = $this->getTable();

        $filteredData = array_filter(
            $chargebeeData,
            fn ($key) => Schema::hasColumn($table, $key),
            ARRAY_FILTER_USE_KEY
        );

        $this->update($filteredData);
    }

    /**
     * Get the Chargebee customer instance for the current user or create one.
     */
    public function createOrGetChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            return $this->asChargebeeCustomer();
        }

        return $this->createAsChargebeeCustomer($options);
    }

    /**
     * Update the Chargebee customer information for the current user or create one.
     */
    public function updateOrCreateChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            return $this->updateChargebeeCustomer($options);
        }

        return $this->createAsChargebeeCustomer($options);
    }

    /**
     * Sync the customer's information to Chargebee.
     */
    public function syncChargebeeCustomerDetails(): Customer
    {
        return $this->updateChargebeeCustomer([
            'firstName' => $this->chargebeeFirstName(),
            'lastName' => $this->chargebeeLastName(),
            'email' => $this->chargebeeEmail(),
            'phone' => $this->chargebeePhone(),
            'billingAddress' => $this->chargebeeBillingAddress(),
            'locale' => $this->chargebeeLocale(),
            'metaData' => $this->chargebeeMetaData(),
        ]);
    }

    /**
     * Sync the customer's information to Chargebee for the current user or create one.
     */
    public function syncOrCreateChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            return $this->syncChargebeeCustomerDetails();
        }

        return $this->createAsChargebeeCustomer($options);
    }

    /**
     * Get the default first name.
     */
    public function chargebeeFirstName(): string|null
    {
        return $this->first_name ?? null;
    }

    /**
     * Get the default last name.
     */
    public function chargebeeLastName(): string|null
    {
        return $this->last_name ?? null;
    }

    /**
     * Get the default email address.
     */
    public function chargebeeEmail(): string|null
    {
        return $this->email ?? null;
    }

    /**
     * Get the default phone number.
     */
    public function chargebeePhone(): string|null
    {
        return $this->phone ?? null;
    }

    /**
     * Get the default billing address.
     */
    public function chargebeeBillingAddress(): array
    {
        return [];
    }

    /**
     * Get the default locale.
     */
    public function chargebeeLocale(): string|null
    {
        return $this->locale ?? null;
    }

    /**
     * Get the default metadata.
     */
    public function chargebeeMetaData(): string
    {
        return $this->chargebee_metadata ?? '';
    }

    /**
     * Determine if the customer is not exempted from taxes.
     */
    public function isNotTaxExempt(): bool
    {
        return $this->asChargebeeCustomer()->taxability === 'taxable';
    }

    /**
     * Determine if the customer is exempted from taxes.
     */
    public function isTaxExempt(): bool
    {
        return $this->asChargebeeCustomer()->taxability === 'exempt';
    }

    /**
     * Get the Chargebee supported currency used by the customer.
     */
    public function preferredCurrency(): string
    {
        return config('cashier.currency');
    }

    /**
     * Format the given amount into a displayable currency.
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->preferredCurrency());
    }

    /**
     * Get the raw total balance of the customer.
     */
    public function rawBalance(): int
    {
        if (! $this->hasChargebeeId()) {
            return 0;
        }

        $customer = $this->asChargebeeCustomer();

        return $customer->promotionalCredits;
    }

    /**
     * Get the total balance of the customer.
     */
    public function balance(): string
    {
        return $this->formatAmount($this->rawBalance());
    }

    /**
     * Credit a customer's balance.
     */
    public function creditBalance(int $amount, string $description = 'Add promotional credits.', array $options = []): CustomerBalanceTransaction
    {
        $result = PromotionalCredit::add(array_merge([
            'customerId' => $this->chargebeeId(),
            'amount' => $amount,
            'description' => $description,
            'currency_code' => $this->preferredCurrency(),
        ], $options));

        return new CustomerBalanceTransaction($this, $result->promotionalCredit());
    }

    /**
     * Debit a customer's balance.
     */
    public function debitBalance(int $amount, string $description = 'Deduct promotional credits.', array $options = []): CustomerBalanceTransaction
    {
        $result = PromotionalCredit::deduct(array_merge([
            'customerId' => $this->chargebeeId(),
            'amount' => $amount,
            'description' => $description,
            'currency_code' => $this->preferredCurrency(),
        ], $options));

        return new CustomerBalanceTransaction($this, $result->promotionalCredit());
    }

    /**
     * Apply a new amount to the customer's balance.
     */
    public function applyBalance(int $amount, string $description = 'Apply balance.', array $options = []): CustomerBalanceTransaction
    {
        $this->assertCustomerExists();

        if ($amount < 0) {
            return $this->debitBalance(abs($amount), $description, $options);
        } else {
            return $this->creditBalance($amount, $description, $options);
        }
    }

    /**
     * Return a customer's balance transactions.
     */
    public function balanceTransactions(int $limit = 10, array $options = []): Collection
    {
        if (! $this->hasChargebeeId()) {
            return new Collection();
        }

        $all = PromotionalCredit::all(array_merge([
            'limit' => $limit,
            'customerId[is]' => $this->chargebeeId(),
        ], $options));

        return collect($all)->map(function ($entry) {
            return $entry->promotionalCredit();
        });
    }

    /*
     * Get the Chargebee billing portal session for this customer.
     */
    public function billingPortalUrl($returnUrl = null, array $options = []): string
    {
        $this->assertCustomerExists();

        $response = PortalSession::create(array_merge([
            'redirect_url' => $returnUrl ?? route('home'),
            'customer' => [
                'id' => $this->chargebeeId(),
            ],
        ], $options));

        return $response->portalSession()->accessUrl;
    }

    /**
     * Generate a redirect response to the customer's Chargebee billing portal session.
     */
    public function redirectToBillingPortal($returnUrl = null, array $options = []): RedirectResponse
    {
        return new RedirectResponse(
            $this->billingPortalUrl($returnUrl, $options)
        );
    }
}
