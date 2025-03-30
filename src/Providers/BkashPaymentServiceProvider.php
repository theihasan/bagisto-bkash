<?php

namespace Webkul\BkashPayment\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\BkashPayment\Services\BkashConfigManager;

class BkashPaymentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'bkash_payment');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'bkash_payment');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->publishes([
            __DIR__.'/../Resources/views'  => resource_path('views/vendor/bkash_payment'),
            __DIR__.'/../Resources/assets' => public_path('vendor/bkash_payment'),
        ], 'bkash-payment-assets');

        $this->app->singleton('bkash.config', function () {
            return new BkashConfigManager;
        });

        // Set bKash config from admin settings after TheIhasan\Bkash is registered
        $this->app->booted(function () {
            $this->setBkashConfig();
        });

        // Listen for payment complete events
        Event::listen('checkout.order.save.after', 'Webkul\BkashPayment\Listeners\OrderPlacement@handle');

        //$this->publishAssets();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/paymentmethods.php', 'payment_methods'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php', 'core'
        );
    }

    /**
     * Set bKash config from admin settings
     *
     * @return void
     */
    protected function setBkashConfig()
    {
        if ($this->app->runningInConsole() || ! $this->checkConfigTableExists()) {
            return;
        }

        try {
            $configManager = $this->app->make('bkash.config');
            $configManager->setConfiguration();
        } catch (\Exception $e) {
            \Log::warning('Unable to set bKash configuration: '.$e->getMessage());
        }
    }

    /**
     * Check if the core_config table exists
     */
    protected function checkConfigTableExists(): bool
    {
        try {
            \DB::table('core_config')->first();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Publish assets
     *
     * @return void
     */
    protected function publishAssets()
    {
        $this->publishes([
            __DIR__.'/../Resources/assets' => public_path('vendor/bkash_payment'),
        ], 'public');

        // Ensure the directory exists
        if (! file_exists(public_path('vendor/bkash_payment/images'))) {
            mkdir(public_path('vendor/bkash_payment/images'), 0755, true);
        }

        if (! file_exists(public_path('vendor/bkash_payment/images/bkash-logo.png'))) {
            copy(
                __DIR__.'/../Resources/assets/images/bkash-logo.png',
                public_path('vendor/bkash_payment/images/bkash-logo.png')
            );
        }
    }
}
