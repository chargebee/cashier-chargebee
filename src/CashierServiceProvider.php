<?php

namespace Chargebee\Cashier;

use BackedEnum;

use Chargebee\Cashier\Console\FeatureEnumCommand;
use Chargebee\Cashier\Console\WebhookCommand;
use Chargebee\Cashier\Contracts\InvoiceRenderer;
use Chargebee\Cashier\Events\WebhookReceived;
use Chargebee\Cashier\Invoices\DompdfInvoiceRenderer;
use Chargebee\Cashier\Listeners\HandleWebhookReceived;
use Chargebee\Cashier\Listeners\UserLoginEventSubscriber;
use Chargebee\Cashier\Contracts\FeatureEnumContract;
use Chargebee\Cashier\Http\Middleware\UserEntitlementCheck;
use Chargebee\Cashier\Contracts\EntitlementAccessVerifier;
use Chargebee\Cashier\Support\DefaultEntitlementAccessVerifier;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
        if (config('cashier.site') && config('cashier.api_key')) {
            Cashier::configureEnvironment();
        }

        $this->registerEventListeners();
        
        if (config('cashier.entitlements.enabled', false)) {
            $this->enableEntitlements();
        }
    }   

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configure();
        $this->bindInvoiceRenderer();
    }

    /**
     * Setup the configuration for Cashier.
     */
    protected function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cashier.php',
            'cashier'
        );
    }

    /**
     * Bind the default invoice renderer.
     *
     * @return void
     */
    protected function bindInvoiceRenderer(): void
    {
        $this->app->bind(InvoiceRenderer::class, function ($app) {
            return $app->make(config('cashier.invoices.renderer', DompdfInvoiceRenderer::class));
        });
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (Cashier::$registersRoutes) {
            Route::group([
                'prefix' => config('cashier.path'),
                'namespace' => 'Chargebee\Cashier\Http\Controllers',
                'as' => 'chargebee.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }
    }

    /**
     * Register the package resources.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $publishesMigrationsMethod = method_exists($this, 'publishesMigrations')
                ? 'publishesMigrations'
                : 'publishes';

            $this->{$publishesMigrationsMethod}([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'cashier-migrations');

            $this->publishes([
                __DIR__.'/../config/cashier.php' => $this->app->configPath('cashier.php'),
            ], 'cashier-config');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/cashier'),
            ], 'cashier-views');
        }
    }
    /**
     * Register the package's commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                WebhookCommand::class,
                FeatureEnumCommand::class
            ]);
        }
    }

    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(
            WebhookReceived::class,
            config('cashier.webhook_listener', HandleWebhookReceived::class)
        );
    }

    protected function enableEntitlements(): void
    {
        // Enable event listener for user authentication
        Event::subscribe(UserLoginEventSubscriber::class);

        // Initialise the route macro, which binds the middleware to the route
        // and reads the required features from the route action.
        \Illuminate\Routing\Route::macro('requiresEntitlement', function (FeatureEnumContract&BackedEnum ...$features) {
            /** @var \Illuminate\Routing\Route $this */
            $this->middleware(UserEntitlementCheck::class);
            
            $action = $this->getAction();
            $action[Constants::REQUIRED_FEATURES_KEY] = $features;
            $this->setAction($action);

            return $this;
        });

        // Initialize access verifier instance, which defaults to our default implementation
        $this->app->singleton(EntitlementAccessVerifier::class, function ($app) {
            /** @var \Illuminate\Contracts\Foundation\Application $app */
            return $app->make(config('cashier.entitlements.access_verifier', DefaultEntitlementAccessVerifier::class));
        });
    }
}
