<?php

namespace Ihasan\Bkash;

use Ihasan\Bkash\Commands\BkashCommand;
use Illuminate\Support\Facades\Http;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BkashServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('bagisto-bkash')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web')
            ->hasMigration('create_bkash_payment_table')
            ->hasCommand(BkashCommand::class)
            ->hasInstallCommand(function($command) {
                $command
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('theihasan/bagisto-bkash');
            });
    }

    public function packageBooted()
    {
        $this->registerBkashHttpMacros();
        
        $this->registerBagistoConfiguration();
    }

    protected function registerBkashHttpMacros(): void
    {
        Http::macro('bkash', function () {
            // Get configuration - use core() if available (Bagisto environment), otherwise use config() helper
            if (function_exists('core')) {
                $isSandbox = core()->getConfigData('sales.payment_methods.bkash.bkash_sandbox') === '1';
                $baseUrl = $isSandbox
                    ? core()->getConfigData('sales.payment_methods.bkash.sandbox_base_url')
                    : core()->getConfigData('sales.payment_methods.bkash.live_base_url');
            } else {
                // Fallback for testing environment - use default URLs
                $isSandbox = config('bagisto-bkash.payment_methods.bkash.sandbox', true);
                $baseUrl = $isSandbox
                    ? 'https://checkout.sandbox.bka.sh/v1.2.0-beta'
                    : 'https://checkout.pay.bka.sh/v1.2.0-beta';
            }

            return Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->baseUrl($baseUrl);
        });

        Http::macro('bkashWithToken', function ($token, $appKey) {
            // Get configuration - use core() if available (Bagisto environment), otherwise use config() helper
            if (function_exists('core')) {
                $isSandbox = core()->getConfigData('sales.payment_methods.bkash.bkash_sandbox') === '1';
                $baseUrl = $isSandbox
                    ? core()->getConfigData('sales.payment_methods.bkash.sandbox_base_url')
                    : core()->getConfigData('sales.payment_methods.bkash.live_base_url');
            } else {
                // Fallback for testing environment - use default URLs
                $isSandbox = config('bagisto-bkash.payment_methods.bkash.sandbox', true);
                $baseUrl = $isSandbox
                    ? 'https://checkout.sandbox.bka.sh/v1.2.0-beta'
                    : 'https://checkout.pay.bka.sh/v1.2.0-beta';
            }

            return Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-APP-Key'     => $appKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->baseUrl($baseUrl);
        });
    }

    public function packageRegistered()
    {
        $this->app->singleton(Bkash::class, function ($app) {
            return new Bkash($app->make(Services\BkashPaymentService::class));
        });
    }

    protected function registerBagistoConfiguration(): void
    {
        if (function_exists('config')) {
            $paymentMethods = config('bagisto-bkash.payment_methods', []);
            foreach ($paymentMethods as $method) {
                config(['payment_methods.' . $method['code'] => $method]);
            }

            // Register system configuration properly - add to existing core config array instead of creating nested structure
            $systemConfig = config('bagisto-bkash.system_config', []);
            $existingCoreConfig = config('core', []);
            foreach ($systemConfig as $config) {
                $existingCoreConfig[] = $config;
            }
            config(['core' => $existingCoreConfig]);
        }
    }
}
