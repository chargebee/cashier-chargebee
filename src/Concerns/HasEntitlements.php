<?php

namespace Chargebee\Cashier\Concerns;

use Chargebee\Cashier\Contracts\EntitlementAccessVerifier;
use Chargebee\Cashier\Contracts\FeatureEnumContract;
use Chargebee\Cashier\Entitlement;
use Chargebee\Cashier\EntitlementErrorCode;
use Chargebee\Cashier\Feature;
use Chargebee\Cashier\Subscription;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $entitlements = collect($this->subscriptions)->flatMap(fn (Subscription $sub) => $sub->getEntitlements());

        return $entitlements;
    }

    /**
     * Get the entitlements for the user
     *
     * @return Collection<Entitlement>
     */
    public function getEntitlements(): Collection
    {
        if (! $this->entitlements) {
            $this->entitlements = $this->fetchEntitlements();
        }

        return $this->entitlements;
    }

    /**
     * Set the entitlements for the user
     *
     * @param  Collection<Entitlement>  $entitlements
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
        $cacheKey = $this->entitlementsCacheKeyPrefix.'_'.$this->id;

        $cachedEntitlements = $cacheStore->get($cacheKey);
        if ($cachedEntitlements) {
            Log::debug('Got entitlements from cache: ', ['cachedEntitlements' => $cachedEntitlements]);
            $this->entitlements = collect($cachedEntitlements)->map(fn ($entitlement) => Entitlement::fromArray($entitlement));
        } else {
            $entitlements = $this->getEntitlements();
            Log::debug('Got entitlements from API: ', ['entitlements' => $entitlements]);
            $cacheExpirySeconds = config('session.lifetime', 120) * 60;
            $cacheStore->put($cacheKey, $entitlements->toArray(), $cacheExpirySeconds);
        }
    }

    /**
     * Check if the given features are missing in the database
     *
     * @param  Collection<FeatureEnumContract>  $features
     * @param  Collection<Feature>  $featureModels
     * @return Collection<FeatureEnumContract>|null
     */
    protected function checkMissingFeatures(Collection $features, Collection $featureModels): ?Collection
    {
        $missingFeatureIds = $features->reject(function ($enum) use ($featureModels) {
            return $featureModels->contains(function ($model) use ($enum) {
                return $enum->id() === $model->chargebee_id;
            });
        });
        if ($missingFeatureIds->count() > 0) {
            return $missingFeatureIds;
        }

        return null;
    }

    /**
     * Check if the user has the given entitlement
     *
     * @param  FeatureEnumContract|array<FeatureEnumContract>  $features
     * @param  ?Request  $request
     * @return bool
     */
    public function hasAccess($features, ?Request $request = null): bool
    {
        $feats = collect($features);
        $featureModels = Feature::whereIn('chargebee_id', $features)->get();
        $request = $request ?? request();
        $entitlementAccessVerifier = app(EntitlementAccessVerifier::class);

        $missingFeatures = $this->checkMissingFeatures($feats, $featureModels);
        if ($missingFeatures && $missingFeatures->count() > 0) {
            Log::error(<<<'EOF'
            Feature(s) missing in database. Run `php artisan cashier:generate-feature-enum` to sync.
            EOF, ['missingFeatures' => $missingFeatures]);

            $entitlementAccessVerifier::handleError($request, EntitlementErrorCode::MISSING_FEATURE_IN_DB, $missingFeatures);

            return false;
        }

        return $entitlementAccessVerifier::hasAccessToFeatures($request, $featureModels);
    }
}
