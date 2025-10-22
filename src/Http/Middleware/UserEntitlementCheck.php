<?php

namespace Chargebee\Cashier\Http\Middleware;

use BackedEnum;
use Closure;
use ReflectionClass;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

use Chargebee\Cashier\Support\RequiresEntitlement;
use Chargebee\Cashier\Contracts\FeatureEnumContract;
use Chargebee\Cashier\Constants;
use Chargebee\Cashier\Concerns\HasEntitlements;

final class UserEntitlementCheck
{
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        if (!$route) {
            throw new HttpException(500, 'No route bound to request.');
        }

        /** @var Authenticatable&HasEntitlements $user */
        $user = $request->user();

        // 1) Controller/method attribute(s)
        $features = $this->featuresFromAttributes($route);

        // 2) Or from route macro (closure routes)
        if (!$features) {
            /** @var null|array<FeatureEnumContract&BackedEnum> $fromAction */
            $fromAction = $route->getAction(Constants::REQUIRED_FEATURES_KEY) ?? null;
            if ($fromAction) {
                $features = $fromAction;
            }
        }
        if ($features) {
            $hasAccess = $user->hasAccess(...$features);
            if (!$hasAccess) {
                throw new HttpException(403, 'You are not authorized to access this resource.');
            }

            $request->attributes->set(Constants::REQUIRED_FEATURES_KEY, $features);
        }

        return $next($request);
    }

    /**
     * @return array<FeatureEnumContract&BackedEnum>
     */
    private function featuresFromAttributes($route): array
    {
        $controller = $route->getController();
        $method     = $route->getActionMethod();
        $found      = [];

        if ($controller) {
            $rc = new ReflectionClass($controller);

            foreach ($rc->getAttributes(RequiresEntitlement::class) as $attr) {
                /** @var RequiresEntitlement $inst */
                $inst = $attr->newInstance();
                array_push($found, ...$inst->features);
            }

            if ($rc->hasMethod($method)) {
                $rm = $rc->getMethod($method);
                foreach ($rm->getAttributes(RequiresEntitlement::class) as $attr) {
                    /** @var RequiresEntitlement $inst */
                    $inst = $attr->newInstance();
                    array_push($found, ...$inst->features);
                }
            }
        }

        return $found;
    }
}
