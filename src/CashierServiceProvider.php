<?php

namespace Chargebee\CashierChargebee;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Chargebee\CashierChargebee\Contracts\InvoiceRenderer;
use Chargebee\CashierChargebee\Events\WebhookReceived;
use Chargebee\CashierChargebee\Invoices\DompdfInvoiceRenderer;
use Chargebee\CashierChargebee\Listeners\HandleWebhookReceived;

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
        Cashier::configureEnvironment();

        Event::listen(
            WebhookReceived::class,
            config('cashier.webhook_listener', HandleWebhookReceived::class)
        );
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
                'namespace' => 'Chargebee\CashierChargebee\Http\Controllers',
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
}
