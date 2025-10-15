<?php

namespace Chargebee\Cashier\Concerns;

use BackedEnum;

use Chargebee\Cashier\Contracts\FeatureEnumContract;
use Chargebee\Cashier\Entitlement;
use Chargebee\Cashier\Subscription;

use Illuminate\Support\Facades\Cache;   
use Illuminate\Support\Facades\Log;

trait HasEntitlements
{
    /**
     * The entitlements for the user, which is cached for use in controllers
     * via the $request->user()->getEntitlements() method
     * 
     * @var array<Entitlement>
     */
    private ?array $entitlements = null;


    /**
     * The prefix for the cache key
     * 
     * @var string
     */
    public string $entitlementsCacheKeyPrefix = 'entitlements';

    /**
     * Get the entitlements for the user
     * 
     * @return array<Entitlement> | null
     */
    private function fetchEntitlements(): ?array
    {
        $entitlements = collect($this->subscriptions)->flatMap(fn(Subscription $sub) => $sub->getEntitlements());
        return $entitlements->toArray();
    }


    /**
     * Get the entitlements for the user
     * 
     * @return array<Entitlement> | null
     */
    public function getEntitlements(): ?array
    {
        if (!$this->entitlements) {
            $this->entitlements = $this->fetchEntitlements();
        }
        return $this->entitlements;
    }

    /**
     * Set the entitlements for the user
     *
     * @param array<Entitlement> $entitlements
     */
    public function setEntitlements(array $entitlements): void
    {
        $this->entitlements = $entitlements;
    }

    /**
     * Ensure the entitlements are loaded from the cache or the API
     */
    public function ensureEntitlements(): void
    {
        $cachedEntitlements = Cache::get($this->entitlementsCacheKeyPrefix);
        if ($cachedEntitlements) {
            Log::debug('Got entitlements from cache: ' , ['cachedEntitlements' => $cachedEntitlements]);
            // Convert the cached entitlements to an array of Entitlement objects
            $this->entitlements = collect($cachedEntitlements)->map(fn($entitlement) => Entitlement::fromArray($entitlement))->toArray();
        } else {
            $entitlements = $this->getEntitlements();   
            Log::debug('Got entitlements from API: ' , ['entitlements' => $entitlements]);
            $cacheExpirySeconds = config('session.lifetime', 120) * 60;
            Cache::put($this->entitlementsCacheKeyPrefix, $entitlements, $cacheExpirySeconds);
        }
    }

    /**
     * Check if the user has the given entitlement
     *
     * @param FeatureEnumContract&BackedEnum ...$features
     * @return Entitlement[]
     */
    public function hasAccess(FeatureEnumContract&BackedEnum ...$features): array
    {
        $entitlements = collect($this->getEntitlements())->intersectUsing($features, function (Entitlement $entitlement, FeatureEnumContract&BackedEnum $feature) {
            return $entitlement->providesFeature($feature);
        });
        return $entitlements->toArray();
    }
}
