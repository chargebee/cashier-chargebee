<?php

namespace Chargebee\Cashier\Contracts;

use BackedEnum;

interface FeatureEnumContract extends BackedEnum
{
    /**
     * Returns the chargebee id of the feature.
     */
    public function id(): string;

    /**
     * Returns array of FeatureEnum
     *
     * @param  array<string>  $featureIds
     * @return array<FeatureEnumContract>
     */
    public static function fromArray(array $featureIds): array;
}
