<?php

namespace Chargebee\Cashier\Contracts;

use Chargebee\Cashier\EntitlementErrorCode;
use Chargebee\Cashier\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface EntitlementAccessVerifier
{
    /**
     * For the given user, decide if feature is accessible to them. The entitlements
     * for the user are accessible via $request->user()->getEntitlements(). The implementation in the
     * app will need to consider variour factors like feature type, value, levels, etc.
     *
     * If multiple features are defined on the route, those are passed as an array to this method.
     * Depending on the business need, you may choose to apply a AND or OR logic to the features.
     *
     * If you also track usage of these features in your app, apply the required logic to verify
     * if the usage is within the entitled limits.
     *
     * @param  Request  $request
     * @param  Collection<Feature>  $features
     * @return bool
     */
    public static function hasAccessToFeatures(Request $request, Collection $features): bool;

    /**
     * When hasAccessToFeatures returns false, this method will be called to handle the access denied case.
     * You can throw an exception, return a response, or redirect to a different page.
     *
     * @param  Request  $request
     * @param  EntitlementErrorCode  $error
     */
    public static function handleError(Request $request, EntitlementErrorCode $error): void;
}
