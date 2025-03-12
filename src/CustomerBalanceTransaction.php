<?php

namespace Chargebee\CashierChargebee;

use ChargeBee\ChargeBee\Models\PromotionalCredit;
use Chargebee\CashierChargebee\Exceptions\InvalidCustomerBalanceTransaction;

class CustomerBalanceTransaction
{
    /**
     * The Chargebee model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The Chargebee CustomerBalanceTransaction instance.
     *
     * @var \ChargeBee\ChargeBee\Models\PromotionalCredit
     */
    protected $transaction;

    /**
     * Create a new CustomerBalanceTransaction instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  \ChargeBee\ChargeBee\Models\PromotionalCredit  $transaction
     * @return void
     *
     * @throws \Chargebee\CashierChargebee\Exceptions\InvalidCustomerBalanceTransaction
     */
    public function __construct($owner, PromotionalCredit $transaction)
    {
        if ($owner->chargebee_id !== $transaction->customerId) {
            throw InvalidCustomerBalanceTransaction::invalidOwner($transaction, $owner);
        }

        $this->owner = $owner;
        $this->transaction = $transaction;
    }

    /**
     * Get the total transaction amount.
     *
     * @return string
     */
    public function amount(): string
    {
        return $this->formatAmount($this->rawAmount());
    }

    /**
     * Get the raw total transaction amount.
     *
     * @return int
     */
    public function rawAmount(): mixed
    {
        return $this->transaction->amount;
    }

    /**
     * Get the ending balance.
     *
     * @return string
     */
    public function endingBalance(): string
    {
        return $this->formatAmount($this->rawEndingBalance());
    }

    /**
     * Get the raw ending balance.
     *
     * @return int
     */
    public function rawEndingBalance(): mixed
    {
        return $this->transaction->closingBalance;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->transaction->currency);
    }

    /**
     * Get the Chargebee PromotionalCredit instance.
     *
     * @return \ChargeBee\ChargeBee\Models\PromotionalCredit
     */
    public function asChargebeeCustomerBalanceTransaction()
    {
        return $this->transaction;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): mixed
    {
        return $this->asChargebeeCustomerBalanceTransaction()->getValues();
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
        return $this->transaction->{$key};
    }
}
