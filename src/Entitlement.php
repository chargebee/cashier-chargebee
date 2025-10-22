<?php

namespace Chargebee\Cashier;

use BackedEnum;
use Chargebee\Cashier\Contracts\FeatureEnumContract;
use Chargebee\Resources\SubscriptionEntitlement\SubscriptionEntitlement as ChargebeeSubscriptionEntitlement;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class Entitlement implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee Subscription Entitlement instance.
     *
     * @var \Chargebee\Resources\SubscriptionEntitlement\SubscriptionEntitlement
     */
    protected $entitlement;

    protected Feature $feature;

    /**
     * Create a new Entitlement instance.
     *
     * @param  \Chargebee\Resources\SubscriptionEntitlement\SubscriptionEntitlement  $entitlement
     * @return void
     */
    public function __construct(ChargebeeSubscriptionEntitlement $entitlement)
    {
        $this->entitlement = $entitlement;
    }

    /**
     * Set and return the Feature instance.
     *
     * @return \Chargebee\Cashier\Feature
     */
    public function feature(): Feature
    {
        $this->feature = Feature::find($this->feature_id);

        return $this->feature;
    }

    /**
     * Determine if the Entitlement provides the feature.
     *
     * @return bool
     */
    public function providesFeature(FeatureEnumContract&BackedEnum $feature): bool
    {
        return $this->entitlement->feature_id === $feature->id();
    }

    /**
     * Get the array representation of the Entitlement.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->entitlement->toArray();
    }

    /**
     * Get the JSON representation of the Entitlement.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get the serialized representation of the Entitlement.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the value of the given key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key): mixed
    {
        return $this->entitlement->{$key};
    }

    /**
     * Returns the Entitlement instance from an array
     *
     * @param  array  $array
     * @return \Chargebee\Cashier\Entitlement
     */
    public static function fromArray(array $array): self
    {
        return new self(ChargebeeSubscriptionEntitlement::from($array));
    }
}
