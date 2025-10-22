<?php

namespace Chargebee\Cashier\Support;

use Attribute;
use BackedEnum;
use Chargebee\Cashier\Contracts\FeatureEnumContract;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RequiresEntitlement
{
    /**
     * @var list<FeatureEnumContract&BackedEnum>
     */
    public array $features;

    /**
     * @param  FeatureEnumContract&BackedEnum  ...$features
     */
    public function __construct(FeatureEnumContract&BackedEnum ...$features)
    {
        $this->features = $features;
    }
}
