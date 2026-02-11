<?php

namespace Chargebee\Cashier;

class Constants
{
    public const REQUIRED_FEATURES_KEY = 'chargebee.required_features';
}

enum EntitlementErrorCode
{
    case MISSING_FEATURE_IN_DB;
    case ACCESS_DENIED;
}
