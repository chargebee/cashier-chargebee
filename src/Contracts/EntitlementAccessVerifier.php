<?php

namespace Chargebee\Cashier\Contracts;

use Chargebee\Cashier\Feature;

interface EntitlementAccessVerifier
{
    /**
     * Verify if the user has access to the feature.
     *
     * @param array<\Chargebee\Cashier\Entitlement> $entitlements
     * @param \Chargebee\Cashier\Feature $feature
     * @return bool
     */
    public static function hasAccessToFeature(array $entitlements, Feature $feature): bool;
}
