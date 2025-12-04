<?php

namespace Ihasan\Bkash\Tests\Feature;

use Ihasan\Bkash\Models\BkashPayment;
use Ihasan\Bkash\Payment\Bkash;
use Ihasan\Bkash\PaymentStatus;
use Ihasan\Bkash\Services\BkashPaymentService;
use Ihasan\Bkash\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private BkashPaymentService $service;

    private Bkash $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BkashPaymentService(
            $this->app->make(OrderRepository::class),
            $this->app->make(InvoiceRepository::class)
        );

        $this->payment = new Bkash(
            $this->app->make(OrderRepository::class),
            $this->app->make(InvoiceRepository::class),
            $this->service
        );
    }

    #[Test]
    public function it_completes_full_payment_flow_successfully(): void
    {
        // Step 1: Create payment
        $this->mockSuccessfulTokenResponse();
        $this->mockSuccessfulPaymentCreation();

        $cart = $this->createMockCart();
        $paymentData = $this->service->createPayment($cart);

        $this->assertEquals('TR0011test123456789', $paymentData['paymentID']);
        $this->assertDatabaseHas('bkash_payments', [
            'payment_id' => 'TR0011test123456789',
            'cart_id' => 123,
            'status' => PaymentStatus::INITIATED->value,
        ]);

        // Step 2: Simulate user payment and callback
        $this->mockSuccessfulPaymentExecution();

        $callbackRequest = new Request([
            'paymentID' => 'TR0011test123456789',
            'status' => 'success',
            'signature' => 'test_signature',
        ]);

        // Mock cart retrieval for callback
        $this->app->bind(\Webkul\Checkout\Models\Cart::class, function () use ($cart) {
            return $cart;
        });

        // Process callback would normally create order, but we'll test the payment execution part
        $executionResult = $this->service->executePayment('TR0011test123456789');

        $this->assertEquals('TR0011test123456789', $executionResult['paymentID']);
        $this->assertEquals('Completed', $executionResult['transactionStatus']);
    }

    #[Test]
    public function it_handles_payment_creation_and_stores_metadata(): void
    {
        $this->mockSuccessfulTokenResponse();
        $this->mockSuccessfulPaymentCreation();

        $cart = $this->createMockCart();
        $paymentData = $this->service->createPayment($cart);

        $payment = BkashPayment::where('payment_id', 'TR0011test123456789')->first();

        $this->assertNotNull($payment);
        $this->assertEquals('TR0011test123456789', $payment->payment_id);
        $this->assertEquals('100.00', $payment->amount);
        $this->assertEquals('INV123', $payment->invoice_number);
        $this->assertEquals(123, $payment->cart_id);
        $this->assertEquals(PaymentStatus::INITIATED->value, $payment->status);

        $meta = json_decode($payment->meta, true);
        $this->assertIsArray($meta);
        $this->assertEquals('TR0011test123456789', $meta['paymentID']);
        $this->assertEquals('Initiated', $meta['transactionStatus']);
    }

    #[Test]
    public function it_generates_correct_redirect_url(): void
    {
        $this->mockSuccessfulTokenResponse();
        $this->mockSuccessfulPaymentCreation();

        // Mock cart() helper
        $this->app->bind('cart', function () {
            return new class
            {
                public function getCart()
                {
                    return (object) [
                        'id' => 123,
                        'customer_email' => 'test@example.com',
                        'grand_total' => 100.00,
                    ];
                }
            };
        });

        $redirectUrl = $this->payment->getRedirectUrl();

        $this->assertEquals('https://sandbox.payment.bkash.com/?paymentId=TR0011test123456789', $redirectUrl);
    }

    #[Test]
    public function it_handles_callback_with_success_status(): void
    {
        // Create a payment record first
        BkashPayment::create([
            'payment_id' => 'TR0011test123456789',
            'token' => 'test_token',
            'amount' => '100.00',
            'invoice_number' => 'INV123',
            'cart_id' => 123,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode(['test' => 'data']),
        ]);

        $this->mockSuccessfulTokenResponse();
        $this->mockSuccessfulPaymentExecution();

        $request = new Request([
            'paymentID' => 'TR0011test123456789',
            'status' => 'success',
            'signature' => 'test_signature',
            'apiVersion' => '1.2.0-beta',
        ]);

        // Mock cart and order creation dependencies
        $this->mockCartAndOrderCreation();

        $response = $this->service->processCallback($request);

        // Should redirect to success page
        $this->assertEquals(302, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_callback_with_failure_status(): void
    {
        // Create a payment record first
        BkashPayment::create([
            'payment_id' => 'TR0011test123456789',
            'token' => 'test_token',
            'amount' => '100.00',
            'invoice_number' => 'INV123',
            'cart_id' => 123,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode(['test' => 'data']),
        ]);

        $request = new Request([
            'paymentID' => 'TR0011test123456789',
            'status' => 'failure',
            'signature' => 'test_signature',
        ]);

        $response = $this->service->processCallback($request);

        // Should redirect back to cart with error
        $this->assertEquals(302, $response->getStatusCode());

        // Check payment status was updated
        $payment = BkashPayment::where('payment_id', 'TR0011test123456789')->first();
        $this->assertEquals('failure', $payment->status);
    }

    #[Test]
    public function it_handles_callback_with_cancel_status(): void
    {
        // Create a payment record first
        BkashPayment::create([
            'payment_id' => 'TR0011test123456789',
            'token' => 'test_token',
            'amount' => '100.00',
            'invoice_number' => 'INV123',
            'cart_id' => 123,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode(['test' => 'data']),
        ]);

        $request = new Request([
            'paymentID' => 'TR0011test123456789',
            'status' => 'cancel',
            'signature' => 'test_signature',
        ]);

        $response = $this->service->processCallback($request);

        // Should redirect back to cart with error message
        $this->assertEquals(302, $response->getStatusCode());

        // Check payment status was updated
        $payment = BkashPayment::where('payment_id', 'TR0011test123456789')->first();
        $this->assertEquals('cancel', $payment->status);
    }

    #[Test]
    public function it_handles_missing_cart_during_callback(): void
    {
        // Create a payment record with non-existent cart
        BkashPayment::create([
            'payment_id' => 'TR0011test123456789',
            'token' => 'test_token',
            'amount' => '100.00',
            'invoice_number' => 'INV123',
            'cart_id' => 999, // Non-existent cart
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode(['test' => 'data']),
        ]);

        $request = new Request([
            'paymentID' => 'TR0011test123456789',
            'status' => 'success',
            'signature' => 'test_signature',
        ]);

        $response = $this->service->processCallback($request);

        // Should redirect back to cart with error
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue(Session::has('error'));
    }

    #[Test]
    public function it_handles_invalid_payment_id_in_callback(): void
    {
        $request = new Request([
            'paymentID' => 'INVALID_PAYMENT_ID',
            'status' => 'success',
            'signature' => 'test_signature',
        ]);

        $response = $this->service->processCallback($request);

        // Should redirect back to cart with error
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue(Session::has('error'));
    }

    #[Test]
    public function it_validates_payment_status_before_execution(): void
    {
        // Create completed payment
        BkashPayment::create([
            'payment_id' => 'TR0011test123456789',
            'token' => 'test_token',
            'amount' => '100.00',
            'invoice_number' => 'INV123',
            'cart_id' => 123,
            'status' => PaymentStatus::SUCCESS->value,
            'meta' => json_encode(['test' => 'data']),
        ]);

        $request = new Request([
            'paymentID' => 'TR0011test123456789',
            'status' => 'success',
            'signature' => 'test_signature',
        ]);

        // Should not find the payment because it's looking for PENDING/INITIATED status
        $response = $this->service->processCallback($request);

        $this->assertEquals(302, $response->getStatusCode());
    }

    private function mockCartAndOrderCreation(): void
    {
        // Mock cart
        $this->app->bind(\Webkul\Checkout\Models\Cart::class, function () {
            $cart = new class
            {
                public $id = 123;

                public function find($id)
                {
                    return $this;
                }
            };

            return $cart;
        });

        // Mock Cart facade
        $this->app->bind('Webkul\Checkout\Facades\Cart', function () {
            return new class
            {
                public static function setCart($cart)
                {
                    return true;
                }

                public static function getCart()
                {
                    return (object) ['id' => 123, 'grand_total' => 100.00];
                }

                public static function deActivateCart()
                {
                    return true;
                }
            };
        });

        // Mock session
        Session::shouldReceive('put')->with('order_id', \Mockery::any())->andReturn(true);
        Session::shouldReceive('flash')->with('order', \Mockery::any())->andReturn(true);
    }
}
