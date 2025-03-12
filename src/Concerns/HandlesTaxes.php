<?php

namespace Chargebee\Cashier\Concerns;

use Chargebee\Cashier\Cashier;

trait HandlesTaxes
{
    /**
     * The IP address of the customer used to determine the tax location.
     */
    public $customerIpAddress;

    /**
     * The pre-collected billing address used to estimate tax rates when performing "one-off" charges.
     */
    public array $estimationBillingAddress = [];

    /**
     * Indicates if Tax IDs should be collected during a Chargebee Checkout session.
     */
    public bool $collectTaxIds = false;

    /**
     * Set the IP address of the customer used to determine the tax location.
     */
    public function withTaxIpAddress(?string $ipAddress): self
    {
        $this->customerIpAddress = $ipAddress;

        return $this;
    }

    /**
     * Set a pre-collected billing address used to estimate tax rates when performing "one-off" charges.
     */
    public function withTaxAddress(string $country, ?string $postalCode = null, ?string $state = null): self
    {
        $this->estimationBillingAddress = array_filter([
            'country' => $country,
            'postal_code' => $postalCode,
            'state' => $state,
        ]);

        return $this;
    }

    /**
     * Get the payload for Chargebee automatic tax calculation.
     */
    protected function automaticTaxPayload(): ?array
    {
        return array_filter([
            'customer_ip_address' => $this->customerIpAddress,
            'enabled' => $this->isAutomaticTaxEnabled(),
            'estimation_billing_address' => $this->estimationBillingAddress,
        ]);
    }

    /**
     * Determine if automatic tax is enabled.
     */
    protected function isAutomaticTaxEnabled(): bool
    {
        return Cashier::$calculatesTaxes;
    }

    /**
     * Indicate that Tax IDs should be collected during a Chargebee Checkout session.
     */
    public function collectTaxIds(): self
    {
        $this->collectTaxIds = true;

        return $this;
    }
}
