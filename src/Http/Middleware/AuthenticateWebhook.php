<?php

namespace Chargebee\CashierChargebee\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWebhook
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('cashier.webhook.username');
        $password = config('cashier.webhook.password');

        if (! $request->hasHeader('Authorization')) {
            return new Response('Unauthorized', 401);
        }

        $authorization = $request->header('Authorization');
        if (str_starts_with($authorization, 'Basic ')) {
            $credentials = base64_decode(substr($authorization, 6));
            [$requestUsername, $requestPassword] = explode(':', $credentials, 2);

            if ($requestUsername === $username && $requestPassword === $password) {
                return $next($request);
            }
        }

        return new Response('Unauthorized', 401);
    }
}
