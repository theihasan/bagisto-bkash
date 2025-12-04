<?php

namespace Ihasan\Bkash\Tests;

use Ihasan\Bkash\BkashServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setupBkashConfiguration();
        $this->setupDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            BkashServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function ($config) {
            $config->set('app.key', 'base64:ZGfpHPsX/nrS0fy+v6J4OEZ8xHBOzKhKZfLn7sV8mQI=');
            $config->set('database.default', 'testbench');
            $config->set('database.connections.testbench', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        });
    }

    protected function setupBkashConfiguration(): void
    {
        Config::set('sales.payment_methods.bkash', [
            'bkash_sandbox' => '1',
            'sandbox_base_url' => 'https://checkout.sandbox.bka.sh/v1.2.0-beta',
            'live_base_url' => 'https://checkout.pay.bka.sh/v1.2.0-beta',
            'bkash_username' => 'test_username',
            'bkash_password' => 'test_password',
            'bkash_app_key' => 'test_app_key',
            'bkash_app_secret' => 'test_app_secret',
        ]);
    }

    protected function setupDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function mockSuccessfulTokenResponse(): void
    {
        Http::fake([
            '*/checkout/token/grant' => Http::response([
                'id_token' => 'mock_token_12345',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_token' => 'mock_refresh_token',
            ], 200),
        ]);
    }

    protected function mockFailedTokenResponse(): void
    {
        Http::fake([
            '*/checkout/token/grant' => Http::response([
                'statusCode' => '2001',
                'statusMessage' => 'Invalid credentials',
            ], 401),
        ]);
    }

    protected function mockSuccessfulPaymentCreation(): void
    {
        Http::fake([
            '*/checkout/payment/create' => Http::response([
                'paymentID' => 'TR0011test123456789',
                'bkashURL' => 'https://sandbox.payment.bkash.com/?paymentId=TR0011test123456789',
                'callbackURL' => 'http://localhost/bkash/callback',
                'successCallbackURL' => 'http://localhost/bkash/callback?status=success',
                'failureCallbackURL' => 'http://localhost/bkash/callback?status=failure',
                'cancelledCallbackURL' => 'http://localhost/bkash/callback?status=cancel',
                'amount' => '100.00',
                'intent' => 'sale',
                'currency' => 'BDT',
                'paymentCreateTime' => '2024-01-01T12:00:00:000 GMT+0600',
                'transactionStatus' => 'Initiated',
                'merchantInvoiceNumber' => 'INV123',
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
            ], 200),
        ]);
    }

    protected function mockSuccessfulPaymentExecution(): void
    {
        Http::fake([
            '*/checkout/payment/execute/*' => Http::response([
                'paymentID' => 'TR0011test123456789',
                'trxID' => 'TXN123456789',
                'transactionStatus' => 'Completed',
                'amount' => '100.00',
                'currency' => 'BDT',
                'intent' => 'sale',
                'paymentExecuteTime' => '2024-01-01T12:05:00:000 GMT+0600',
                'merchantInvoiceNumber' => 'INV123',
                'payerType' => 'Customer',
                'payerReference' => 'test@example.com',
                'customerMsisdn' => '01700000000',
                'payerAccount' => '01700000000',
                'maxRefundableAmount' => '100.00',
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
            ], 200),
        ]);
    }

    protected function mockFailedPaymentExecution(): void
    {
        Http::fake([
            '*/checkout/payment/execute/*' => Http::response([
                'statusCode' => '2117',
                'statusMessage' => 'Payment execution already been called before',
            ], 400),
        ]);
    }

    protected function createMockCart(): object
    {
        return (object) [
            'id' => 123,
            'customer_email' => 'test@example.com',
            'grand_total' => 100.00,
        ];
    }
}