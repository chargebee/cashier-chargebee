<?php

namespace Chargebee\Cashier\Contracts;

use Chargebee\Cashier\Feature;
use Chargebee\Cashier\Concerns\HasEntitlements;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;

interface EntitlementAccessVerifier
{
    /**
     * For the given user, decide if feature is accessible to them. The entitlements
     * for the user are accessible via $user->getEntitlements(). The implementation in the
     * app will need to consider variour factors like feature type, value, levels, etc.
     * 
     * If multiple features are defined on the route, those are passed as an array to this method.
     * Depending on the business need, you may choose to apply a AND or OR logic to the features.
     * 
     * If you also track usage of these features in your app, apply the required logic to verify
     * if the usage is within the entitled limits.
     *
     * @param Authenticatable&HasEntitlements $user The user to check access for
     * @param Collection<Feature> $features
     * @return bool
     */
    public static function hasAccessToFeatures($user, Collection $features): bool;
}
