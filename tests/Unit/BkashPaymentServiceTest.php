<?php

namespace Ihasan\Bkash\Tests\Unit;

use Ihasan\Bkash\Exceptions\ConfigurationException;
use Ihasan\Bkash\Exceptions\PaymentCreationException;
use Ihasan\Bkash\Exceptions\TokenException;
use Ihasan\Bkash\Services\BkashPaymentService;
use Ihasan\Bkash\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;

class BkashPaymentServiceTest extends TestCase
{
    private BkashPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BkashPaymentService(
            $this->app->make(OrderRepository::class),
            $this->app->make(InvoiceRepository::class)
        );
    }

    #[Test]
    public function it_can_get_credentials_from_configuration(): void
    {
        $credentials = $this->service->getCredentials();

        $this->assertIsArray($credentials);
        $this->assertArrayHasKey('username', $credentials);
        $this->assertArrayHasKey('password', $credentials);
        $this->assertArrayHasKey('app_key', $credentials);
        $this->assertArrayHasKey('app_secret', $credentials);
        $this->assertArrayHasKey('base_url', $credentials);
        $this->assertArrayHasKey('sandbox', $credentials);

        $this->assertEquals('test_username', $credentials['username']);
        $this->assertEquals('test_password', $credentials['password']);
        $this->assertEquals('test_app_key', $credentials['app_key']);
        $this->assertEquals('test_app_secret', $credentials['app_secret']);
        $this->assertEquals('https://checkout.sandbox.bka.sh/v1.2.0-beta', $credentials['base_url']);
        $this->assertTrue($credentials['sandbox']);
    }

    #[Test]
    public function it_throws_exception_for_missing_credentials(): void
    {
        Config::set('sales.payment_methods.bkash.bkash_username', null);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing bkash configuration: username');

        $this->service->getCredentials();
    }

    #[Test]
    public function it_can_get_token_successfully(): void
    {
        $this->mockSuccessfulTokenResponse();

        $token = $this->service->getToken();

        $this->assertEquals('mock_token_12345', $token);

        // Verify token is cached
        $this->assertTrue(Cache::has('bkash_token'));
        $this->assertEquals('mock_token_12345', Cache::get('bkash_token'));
    }

    #[Test]
    public function it_returns_cached_token_when_available(): void
    {
        Cache::put('bkash_token', 'cached_token_123', now()->addHour());

        Http::fake(); // No HTTP calls should be made

        $token = $this->service->getToken();

        $this->assertEquals('cached_token_123', $token);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_throws_exception_for_failed_token_request(): void
    {
        $this->mockFailedTokenResponse();

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('Failed to get bkash token: Invalid credentials');

        $this->service->getToken();
    }

    #[Test]
    public function it_can_create_payment_successfully(): void
    {
        $this->mockSuccessfulTokenResponse();
        $this->mockSuccessfulPaymentCreation();

        $cart = $this->createMockCart();
        $result = $this->service->createPayment($cart);

        $this->assertIsArray($result);
        $this->assertEquals('TR0011test123456789', $result['paymentID']);
        $this->assertEquals('Initiated', $result['transactionStatus']);
        $this->assertEquals('0000', $result['statusCode']);

        // Verify payment is saved to database
        $this->assertDatabaseHas('bkash_payments', [
            'payment_id' => 'TR0011test123456789',
            'amount' => '100.00',
            'cart_id' => 123,
            'invoice_number' => 'INV123',
        ]);
    }

    #[Test]
    public function it_throws_exception_for_failed_payment_creation(): void
    {
        $this->mockSuccessfulTokenResponse();

        Http::fake([
            '*/checkout/payment/create' => Http::response([
                'statusCode' => '2001',
                'statusMessage' => 'Invalid request',
            ], 400),
        ]);

        $cart = $this->createMockCart();

        $this->expectException(PaymentCreationException::class);
        $this->expectExceptionMessage('Failed to create bkash payment: Invalid request');

        $this->service->createPayment($cart);
    }

    #[Test]
    public function it_can_execute_payment_successfully(): void
    {
        $this->mockSuccessfulTokenResponse();
        $this->mockSuccessfulPaymentExecution();

        $result = $this->service->executePayment('TR0011test123456789');

        $this->assertIsArray($result);
        $this->assertEquals('TR0011test123456789', $result['paymentID']);
        $this->assertEquals('TXN123456789', $result['trxID']);
        $this->assertEquals('Completed', $result['transactionStatus']);
        $this->assertEquals('0000', $result['statusCode']);
    }

    #[Test]
    public function it_throws_exception_for_failed_payment_execution(): void
    {
        $this->mockSuccessfulTokenResponse();
        $this->mockFailedPaymentExecution();

        $this->expectException(PaymentCreationException::class);
        $this->expectExceptionMessage('Payment execution failed: Payment execution already been called before');

        $this->service->executePayment('TR0011test123456789');
    }

    #[Test]
    public function it_can_query_payment_status(): void
    {
        $this->mockSuccessfulTokenResponse();

        Http::fake([
            '*/checkout/payment/query/*' => Http::response([
                'paymentID' => 'TR0011test123456789',
                'transactionStatus' => 'Completed',
                'amount' => '100.00',
                'currency' => 'BDT',
                'statusCode' => '0000',
                'statusMessage' => 'Successful',
            ], 200),
        ]);

        $result = $this->service->queryPayment('TR0011test123456789');

        $this->assertIsArray($result);
        $this->assertEquals('TR0011test123456789', $result['paymentID']);
        $this->assertEquals('Completed', $result['transactionStatus']);
        $this->assertEquals('0000', $result['statusCode']);
    }

    #[Test]
    public function it_throws_exception_for_failed_payment_query(): void
    {
        $this->mockSuccessfulTokenResponse();

        Http::fake([
            '*/checkout/payment/query/*' => Http::response([
                'statusCode' => '2001',
                'statusMessage' => 'Payment not found',
            ], 404),
        ]);

        $this->expectException(PaymentCreationException::class);
        $this->expectExceptionMessage('Payment query failed: Payment not found');

        $this->service->queryPayment('INVALID_PAYMENT_ID');
    }

    #[Test]
    public function it_builds_correct_payment_payload(): void
    {
        $this->mockSuccessfulTokenResponse();

        // Capture the request payload
        Http::fake([
            '*/checkout/payment/create' => function ($request) {
                $payload = $request->data();

                $this->assertEquals('0011', $payload['mode']);
                $this->assertEquals('test@example.com', $payload['payerReference']);
                $this->assertEquals('100.00', $payload['amount']);
                $this->assertEquals('BDT', $payload['currency']);
                $this->assertEquals('sale', $payload['intent']);
                $this->assertEquals('INV123', $payload['merchantInvoiceNumber']);
                $this->assertStringContains('/bkash/callback', $payload['callbackURL']);

                return Http::response(['statusCode' => '0000'], 200);
            },
        ]);

        $cart = $this->createMockCart();
        $this->service->createPayment($cart);
    }

    #[Test]
    public function it_handles_live_environment_configuration(): void
    {
        Config::set('sales.payment_methods.bkash.bkash_sandbox', '0');

        $credentials = $this->service->getCredentials();

        $this->assertEquals('https://checkout.pay.bka.sh/v1.2.0-beta', $credentials['base_url']);
        $this->assertFalse($credentials['sandbox']);
    }

    #[Test]
    public function it_logs_payment_operations(): void
    {
        Log::shouldReceive('info')->once()->with('bKash execute payment:', \Mockery::type('array'));
        Log::shouldReceive('debug')->once()->with('bKash execute response:', \Mockery::type('array'));

        $this->mockSuccessfulTokenResponse();
        $this->mockSuccessfulPaymentExecution();

        $this->service->executePayment('TR0011test123456789');
    }

    #[Test]
    public function it_validates_required_configuration_keys(): void
    {
        $requiredKeys = ['username', 'password', 'app_key', 'app_secret', 'base_url'];

        foreach ($requiredKeys as $key) {
            Config::set("sales.payment_methods.bkash.bkash_{$key}", null);

            $this->expectException(ConfigurationException::class);
            $this->expectExceptionMessage("Missing bkash configuration: {$key}");

            $this->service->getCredentials();

            // Reset for next iteration
            $this->setupBkashConfiguration();
        }
    }

    #[Test]
    public function it_handles_token_cache_expiration(): void
    {
        // Put expired token in cache
        Cache::put('bkash_token', 'expired_token', now()->subMinute());

        $this->mockSuccessfulTokenResponse();

        $token = $this->service->getToken();

        $this->assertEquals('mock_token_12345', $token);
        $this->assertEquals('mock_token_12345', Cache::get('bkash_token'));
    }

    #[Test]
    public function it_uses_correct_http_headers_for_token_request(): void
    {
        Http::fake([
            '*/checkout/token/grant' => function ($request) {
                $this->assertEquals('application/json', $request->header('Content-Type')[0]);
                $this->assertEquals('application/json', $request->header('Accept')[0]);
                $this->assertEquals('test_username', $request->header('username')[0]);
                $this->assertEquals('test_password', $request->header('password')[0]);

                return Http::response(['id_token' => 'mock_token'], 200);
            },
        ]);

        $this->service->getToken();
    }

    #[Test]
    public function it_uses_correct_http_headers_for_authenticated_requests(): void
    {
        Cache::put('bkash_token', 'test_token', now()->addHour());

        Http::fake([
            '*/checkout/payment/execute/*' => function ($request) {
                $this->assertEquals('Bearer test_token', $request->header('Authorization')[0]);
                $this->assertEquals('test_app_key', $request->header('X-APP-Key')[0]);
                $this->assertEquals('application/json', $request->header('Content-Type')[0]);
                $this->assertEquals('application/json', $request->header('Accept')[0]);

                return Http::response(['statusCode' => '0000'], 200);
            },
        ]);

        $this->service->executePayment('TR0011test123456789');
    }
}
