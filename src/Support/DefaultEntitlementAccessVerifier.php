<?php

namespace Chargebee\Cashier\Support;

use Chargebee\Cashier\Contracts\EntitlementAccessVerifier;
use Chargebee\Cashier\Entitlement;
use Chargebee\Cashier\EntitlementErrorCode;
use Chargebee\Cashier\Feature;
use Chargebee\Resources\Feature\Enums\Type as FeatureType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DefaultEntitlementAccessVerifier implements EntitlementAccessVerifier
{
    /**
     * Return true if the entitlements collectively provide all requested features.
     *
     * @param  Request  $request
     * @param  Collection<Feature>  $features
     * @return bool
     */
    public static function hasAccessToFeatures(Request $request, Collection $features): bool
    {
        /** @var Collection<Entitlement> $entitlements */
        $entitlements = $request->user()?->getEntitlements() ?? collect();
        $featureDefaults = config('cashier.entitlements.feature_defaults', []);

        // Every feature must be provided by at least one entitlement (AND logic)
        return $features->every(function ($feature) use ($entitlements, $featureDefaults) {
            return $entitlements->contains(function ($entitlement) use ($feature, $featureDefaults) {
                // If the entitlement does not match the feature being checked, bail.
                if ($entitlement->feature_id !== $feature->chargebee_id) {
                    return false;
                }

                // For the default implementation, we can only check SWITCH feature types.
                // For the others, check if there is a fallback value configured in the config file.
                $hasAccess = match (FeatureType::tryFromValue(strtolower($entitlement->feature_type))) {
                    FeatureType::SWITCH => $entitlement->value,
                    default => $featureDefaults[$feature->chargebee_id] ?? false,
                };

                // coerce the value to a boolean
                return filter_var($hasAccess, FILTER_VALIDATE_BOOLEAN);
            });
        });
    }

    /**
     * Throw a 403 error when access is denied.
     *
     * @param  Request  $request
     * @param  EntitlementErrorCode  $error
     * @param  mixed  $data
     * @return void
     */
    public static function handleError(Request $request, EntitlementErrorCode $error, mixed $data = null): void
    {
        switch ($error) {
            case EntitlementErrorCode::MISSING_FEATURE_IN_DB:
                throw new HttpException(500, 'Error verifying your access to this resource.');
            case EntitlementErrorCode::ACCESS_DENIED:
                throw new HttpException(403, 'You are not authorized to access this resource.');
        }
    }
}
