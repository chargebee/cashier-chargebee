<?php

namespace Chargebee\Cashier\Concerns;

use BackedEnum;

use Chargebee\Cashier\Contracts\FeatureEnumContract;
use Chargebee\Cashier\Entitlement;
use Chargebee\Cashier\Subscription;
use Chargebee\Cashier\Feature;
use Chargebee\Cashier\Contracts\EntitlementAccessVerifier;

use Illuminate\Support\Facades\Cache;   
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

trait HasEntitlements
{
    /**
     * The entitlements for the user, which is cached for use in controllers
     * via the $request->user()->getEntitlements() method
     * 
     * @var Collection<Entitlement>|null
     */
    private ?Collection $entitlements = null;


    /**
     * The prefix for the cache key
     * 
     * @var string
     */
    public string $entitlementsCacheKeyPrefix = 'entitlements';

    /**
     * Get the entitlements for the user
     * 
     * @return Collection<Entitlement>
     */
    private function fetchEntitlements(): Collection
    {
        $entitlements = collect($this->subscriptions)->flatMap(fn(Subscription $sub) => $sub->getEntitlements());
        return $entitlements;
    }


    /**
     * Get the entitlements for the user
     * 
     * @return Collection<Entitlement>
     */
    public function getEntitlements(): Collection
    {
        if (!$this->entitlements) {
            $this->entitlements = $this->fetchEntitlements();
        }
        return $this->entitlements;
    }

    /**
     * Set the entitlements for the user
     *
     * @param Collection<Entitlement> $entitlements
     */
    public function setEntitlements(Collection $entitlements): void
    {
        $this->entitlements = $entitlements;
    }


    protected function entitlementsCacheStore(): CacheRepository
    {
        return Cache::store();
    }

    /**
     * Ensure the entitlements are loaded from the cache or the API
     */
    public function ensureEntitlements(): void
    {
        $cacheStore = $this->entitlementsCacheStore();
        $cacheKey = $this->entitlementsCacheKeyPrefix . '_' . $this->id;

        $cachedEntitlements = $cacheStore->get($cacheKey);
        if ($cachedEntitlements) {
            Log::debug('Got entitlements from cache: ' , ['cachedEntitlements' => $cachedEntitlements]);
            // Convert the cached entitlements to an array of Entitlement objects
            $this->entitlements = collect($cachedEntitlements)->map(fn($entitlement) => Entitlement::fromArray($entitlement));
        } else {
            $entitlements = $this->getEntitlements();   
            Log::debug('Got entitlements from API: ' , ['entitlements' => $entitlements]);
            $cacheExpirySeconds = config('session.lifetime', 120) * 60;
            $cacheStore->put($cacheKey, $entitlements, $cacheExpirySeconds);
        }
    }

    /**
     * Check if the user has the given entitlement
     *
     * @param FeatureEnumContract&BackedEnum ...$features
     * @return bool
     */
    public function hasAccess(FeatureEnumContract&BackedEnum ...$features): bool
    {
        $featureModels = Feature::whereIn('chargebee_id', $features)->get();
        $feats = collect($features);
        if ($featureModels->count() != $feats->count()) {
            Log::warning(<<<EOF
            Some features were not found in the database. Please run `php artisan cashier:generate-feature-enum` to sync.
            EOF, ['missingFeatures' => $feats->diff($featureModels)->implode(', ')]);
        }
        return app(EntitlementAccessVerifier::class)::hasAccessToFeatures($this, $featureModels);
    }
}
