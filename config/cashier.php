<?php

use Chargebee\Cashier\Invoices\DompdfInvoiceRenderer;

return [

    /*
    |--------------------------------------------------------------------------
    | Chargebee API Configuration
    |--------------------------------------------------------------------------
    |
    | The site name is used to construct the base URL for all Chargebee API
    | requests, while the API key authenticates your application when making
    | those requests.
    |
    */

    'site' => env('CHARGEBEE_SITE'),

    'api_key' => env('CHARGEBEE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Chargebee Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Webhook requests are authenticated using basic authentication.
    |
    */

    'webhook' => [
        'username' => env('CASHIER_WEBHOOK_USERNAME'),
        'password' => env('CASHIER_WEBHOOK_PASSWORD'),
    ],

    'webhook_listener' => \Chargebee\Cashier\Listeners\HandleWebhookReceived::class,

    /*
    |--------------------------------------------------------------------------
    | Cashier Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI path where Cashier's views, such as the payment
    | verification screen, will be available from. You're free to tweak
    | this path according to your preferences and application design.
    |
    */

    'path' => env('CASHIER_PATH', 'chargebee'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various world currencies that are currently supported via Chargebee.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default en locale
    | verify you have the "intl" PHP extension installed on the system.
    |
    */

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),

    'invoices' => [
        'renderer' => env('CASHIER_INVOICE_RENDERER', DompdfInvoiceRenderer::class),

        'options' => [
            // Supported: 'letter', 'legal', 'A4'
            'paper' => env('CASHIER_PAPER', 'letter'),
        ],
    ],
];
