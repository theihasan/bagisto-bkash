<?php

namespace Ihasan\Bkash;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Ihasan\Bkash\Commands\BkashCommand;

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
            ->hasMigration('create_bagisto_bkash_table')
            ->hasCommand(BkashCommand::class);
    }
}
