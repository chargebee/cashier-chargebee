<?php

namespace Chargebee\Cashier\Support;

use Attribute;
use Chargebee\Cashier\Contracts\FeatureEnumContract;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RequiresEntitlement
{
    /**
     * @var list<FeatureEnumContract>
     */
    public array $features;

    /**
     * @param  FeatureEnumContract  ...$features
     */
    public function __construct(FeatureEnumContract ...$features)
    {
        $this->features = $features;
    }
}
