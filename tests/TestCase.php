<?php

namespace Ihasan\Bkash\Tests;

use Ihasan\Bkash\BkashServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Ihasan\\Bkash\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            BkashServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Load package configuration
        config()->set('bagisto-bkash', [
            'payment_methods' => [
                'bkash' => [
                    'code'        => 'bkash',
                    'title'       => 'BKash',
                    'description' => 'BKash',
                    'class'       => 'Ihasan\Bkash\Payment\Bkash',
                    'active'      => true,
                    'sort'        => 1,
                ],
            ],
            'system_config' => [],
        ]);

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/2025_02_24_181736_create_bkash_payment_table.php';
        $migration->up();
    }
}
