<?php

namespace Chargebee\Cashier\Listeners;

use Chargebee\Cashier\Concerns\HasEntitlements;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Log;
use Illuminate\Events\Dispatcher;
use Illuminate\Contracts\Auth\Authenticatable;

class UserLoginEventSubscriber
{
    /**
     * On user login, set the entitlements in the request attributes, either from redis, or the API
     */
    public function handleUserAuthenticated(Authenticated $event): void {
        /** @var Authenticatable&HasEntitlements $user */
        $user = $event->user;
        
        // Ensure the entitlements are loaded from the cache or the API
        Log::debug('Ensuring entitlements are loaded for user' , ['user' => $user->id]);
        $user->ensureEntitlements();
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Authenticated::class => 'handleUserAuthenticated',
        ];
    }
}
