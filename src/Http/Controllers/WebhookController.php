<?php

namespace Chargebee\CashierChargebee\Http\Controllers;

use Chargebee\CashierChargebee\Events\WebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Handle a Chargebee webhook call.
     */
    public function handleWebhook(Request $request): Response
    {
        $headers = $request->headers->all();

        unset($headers['authorization'], $headers['php-auth-user'], $headers['php-auth-pw']);

        Log::info('Chargebee Webhook Received', [
            'headers' => $headers,
            'payload' => $request->getContent(),
        ]);

        $payload = json_decode($request->getContent(), true);

        WebhookReceived::dispatch($payload);

        return $this->success();
    }

    /**
     * Handle successful calls on the controller.
     */
    protected function success(): Response
    {
        return new Response('Webhook Received', 200);
    }
}
