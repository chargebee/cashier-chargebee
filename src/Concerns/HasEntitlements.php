<?php

namespace Chargebee\Cashier\Concerns;

use Chargebee\Cashier\Contracts\EntitlementAccessVerifier;
use Chargebee\Cashier\Contracts\FeatureEnumContract;
use Chargebee\Cashier\Entitlement;
use Chargebee\Cashier\Feature;
use Chargebee\Cashier\Subscription;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
            $this->entitlements = $cachedEntitlements;
        } else {
            $entitlements = $this->getEntitlements();
            Log::debug('Got entitlements from API: ', ['entitlements' => $entitlements]);
            $cacheExpirySeconds = config('session.lifetime', 120) * 60;
            $cacheStore->put($cacheKey, $entitlements, $cacheExpirySeconds);
        }
    }

    /**
     * Check if the user has the given entitlement
     *
     * @param  FeatureEnumContract  ...$features
     * @return bool
     */
    public function hasAccess(FeatureEnumContract ...$features): bool
    {
        $featureModels = Feature::whereIn('chargebee_id', $features)->get();
        $feats = collect($features);

        // Since we need to read the feature attributes from the DB,
        // we need to ensure that all the features have been synced. If not, throw a 500 error.
        if ($featureModels->count() != $feats->count()) {
            $missingFeatureIds = $feats->reject(function ($enum) use ($featureModels) {
                return $featureModels->contains(function ($model) use ($enum) {
                    return $enum->id() === $model->chargebee_id;
                });
            });

            Log::error(<<<'EOF'
            Feature(s) missing in database. Run `php artisan cashier:generate-feature-enum` to sync.
            EOF, ['missingFeatures' => $missingFeatureIds->implode(', ')]);
            throw new HttpException(500, 'Error verifying your access to this resource.');
        }

        return app(EntitlementAccessVerifier::class)::hasAccessToFeatures($this, $featureModels);
    }
}
