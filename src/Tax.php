<?php

namespace Chargebee\CashierChargebee;

class Tax
{
    /**
     * The total tax amount.
     *
     * @var int
     */
    protected $amount;

    /**
     * The applied currency.
     *
     * @var string
     */
    protected $currency;

    /**
     * The Chargebee TaxRate.
     *
     * @var float
     */
    protected $taxRate;

    /**
     * Create a new Tax instance.
     *
     * @param  int  $amount
     * @param  string  $currency
     * @param  float  $taxRate
     * @return void
     */
    public function __construct($amount, $currency, $taxRate)
    {
        $this->amount = $amount;
        $this->currency = $currency;
        $this->taxRate = $taxRate;
    }

    /**
     * Get the applied currency.
     *
     * @return string
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Get the total tax that was paid (or will be paid).
     *
     * @return string
     */
    public function amount(): string
    {
        return $this->formatAmount($this->amount);
    }

    /**
     * Get the raw total tax that was paid (or will be paid).
     *
     * @return int
     */
    public function rawAmount(): int
    {
        return $this->amount;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount): string
    {
        return Cashier::formatAmount($amount, $this->currency);
    }

    /**
     * @return float
     */
    public function taxRate(): float
    {
        return $this->taxRate;
    }

    /**
     * Dynamically get values from the Chargebee object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        return $this->{$key};
    }
}
