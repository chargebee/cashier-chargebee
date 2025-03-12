<?php

use Illuminate\Support\Facades\Route;
use Chargebee\CashierChargebee\Http\Middleware\AuthenticateWebhook;

Route::post('webhook', 'WebhookController@handleWebhook')->middleware(AuthenticateWebhook::class)->name('webhook');
