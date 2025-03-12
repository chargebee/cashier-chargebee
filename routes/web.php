<?php

use Chargebee\Cashier\Http\Middleware\AuthenticateWebhook;
use Illuminate\Support\Facades\Route;

Route::post('webhook', 'WebhookController@handleWebhook')->middleware(AuthenticateWebhook::class)->name('webhook');
