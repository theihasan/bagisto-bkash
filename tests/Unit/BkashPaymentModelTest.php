<?php

namespace Ihasan\Bkash\Tests\Unit;

use Ihasan\Bkash\Models\BkashPayment;
use Ihasan\Bkash\PaymentStatus;
use Ihasan\Bkash\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class BkashPaymentModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_bkash_payment_record(): void
    {
        $payment = BkashPayment::create([
            'payment_id' => 'TR0011test123456789',
            'token' => 'test_token_123',
            'amount' => '100.50',
            'invoice_number' => 'INV001',
            'cart_id' => 123,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode(['test' => 'data']),
        ]);

        $this->assertInstanceOf(BkashPayment::class, $payment);
        $this->assertEquals('TR0011test123456789', $payment->payment_id);
        $this->assertEquals('test_token_123', $payment->token);
        $this->assertEquals('100.50', $payment->amount);
        $this->assertEquals('INV001', $payment->invoice_number);
        $this->assertEquals(123, $payment->cart_id);
        $this->assertEquals(PaymentStatus::INITIATED->value, $payment->status);
        $this->assertEquals('{"test":"data"}', $payment->meta);
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $payment = new BkashPayment;

        $this->assertEquals('bkash_payments', $payment->getTable());
    }

    #[Test]
    public function it_has_correct_fillable_fields(): void
    {
        $payment = new BkashPayment;

        $expectedFillable = [
            'payment_id',
            'token',
            'amount',
            'invoice_number',
            'cart_id',
            'status',
            'meta',
        ];

        $this->assertEquals($expectedFillable, $payment->getFillable());
    }

    #[Test]
    public function it_casts_meta_field_as_array(): void
    {
        $payment = BkashPayment::create([
            'payment_id' => 'TR0011test123456789',
            'token' => 'test_token_123',
            'amount' => '100.50',
            'invoice_number' => 'INV001',
            'cart_id' => 123,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => ['test' => 'data', 'amount' => 100.50],
        ]);

        $this->assertIsArray($payment->meta);
        $this->assertEquals(['test' => 'data', 'amount' => 100.50], $payment->meta);
    }

    #[Test]
    public function it_can_find_payment_by_payment_id(): void
    {
        BkashPayment::create([
            'payment_id' => 'TR0011unique123',
            'token' => 'test_token',
            'amount' => '50.00',
            'invoice_number' => 'INV002',
            'cart_id' => 456,
            'status' => PaymentStatus::PENDING->value,
            'meta' => json_encode([]),
        ]);

        $payment = BkashPayment::where('payment_id', 'TR0011unique123')->first();

        $this->assertNotNull($payment);
        $this->assertEquals('TR0011unique123', $payment->payment_id);
        $this->assertEquals(456, $payment->cart_id);
    }

    #[Test]
    public function it_can_update_payment_status(): void
    {
        $payment = BkashPayment::create([
            'payment_id' => 'TR0011update123',
            'token' => 'test_token',
            'amount' => '75.00',
            'invoice_number' => 'INV003',
            'cart_id' => 789,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode([]),
        ]);

        $payment->update([
            'status' => PaymentStatus::SUCCESS->value,
            'meta' => ['completed_at' => now()->toISOString()],
        ]);

        $payment->refresh();

        $this->assertEquals(PaymentStatus::SUCCESS->value, $payment->status);
        $this->assertArrayHasKey('completed_at', $payment->meta);
    }

    #[Test]
    public function it_handles_timestamps_correctly(): void
    {
        $payment = BkashPayment::create([
            'payment_id' => 'TR0011timestamp123',
            'token' => 'test_token',
            'amount' => '25.00',
            'invoice_number' => 'INV004',
            'cart_id' => 101,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode([]),
        ]);

        $this->assertNotNull($payment->created_at);
        $this->assertNotNull($payment->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $payment->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $payment->updated_at);
    }

    #[Test]
    public function it_can_query_payments_by_status(): void
    {
        BkashPayment::create([
            'payment_id' => 'TR0011status1',
            'token' => 'test_token',
            'amount' => '30.00',
            'invoice_number' => 'INV005',
            'cart_id' => 201,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode([]),
        ]);

        BkashPayment::create([
            'payment_id' => 'TR0011status2',
            'token' => 'test_token',
            'amount' => '40.00',
            'invoice_number' => 'INV006',
            'cart_id' => 202,
            'status' => PaymentStatus::SUCCESS->value,
            'meta' => json_encode([]),
        ]);

        $initiatedPayments = BkashPayment::where('status', PaymentStatus::INITIATED->value)->get();
        $successfulPayments = BkashPayment::where('status', PaymentStatus::SUCCESS->value)->get();

        $this->assertCount(1, $initiatedPayments);
        $this->assertCount(1, $successfulPayments);
        $this->assertEquals('TR0011status1', $initiatedPayments->first()->payment_id);
        $this->assertEquals('TR0011status2', $successfulPayments->first()->payment_id);
    }

    #[Test]
    public function it_can_query_payments_by_cart_id(): void
    {
        BkashPayment::create([
            'payment_id' => 'TR0011cart1',
            'token' => 'test_token',
            'amount' => '60.00',
            'invoice_number' => 'INV007',
            'cart_id' => 301,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode([]),
        ]);

        BkashPayment::create([
            'payment_id' => 'TR0011cart2',
            'token' => 'test_token',
            'amount' => '70.00',
            'invoice_number' => 'INV008',
            'cart_id' => 302,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode([]),
        ]);

        $cartPayment = BkashPayment::where('cart_id', 301)->first();

        $this->assertNotNull($cartPayment);
        $this->assertEquals('TR0011cart1', $cartPayment->payment_id);
        $this->assertEquals(301, $cartPayment->cart_id);
    }

    #[Test]
    public function it_stores_decimal_amounts_correctly(): void
    {
        $payment = BkashPayment::create([
            'payment_id' => 'TR0011decimal123',
            'token' => 'test_token',
            'amount' => '99.99',
            'invoice_number' => 'INV009',
            'cart_id' => 401,
            'status' => PaymentStatus::INITIATED->value,
            'meta' => json_encode([]),
        ]);

        $this->assertEquals('99.99', $payment->amount);

        // Test retrieving from database
        $payment->refresh();
        $this->assertEquals('99.99', $payment->amount);
    }

    #[Test]
    public function it_handles_large_meta_data(): void
    {
        $largeMeta = [
            'payment_details' => [
                'paymentID' => 'TR0011large123',
                'trxID' => 'TXN123456789',
                'transactionStatus' => 'Completed',
                'amount' => '500.00',
                'currency' => 'BDT',
                'intent' => 'sale',
                'paymentExecuteTime' => '2024-01-01T12:05:00:000 GMT+0600',
                'merchantInvoiceNumber' => 'INV010',
                'payerType' => 'Customer',
                'payerReference' => 'customer@example.com',
                'customerMsisdn' => '01700000000',
                'payerAccount' => '01700000000',
                'maxRefundableAmount' => '500.00',
            ],
            'additional_info' => [
                'user_agent' => 'Mozilla/5.0 (compatible; Test)',
                'ip_address' => '127.0.0.1',
                'created_at' => now()->toISOString(),
            ],
        ];

        $payment = BkashPayment::create([
            'payment_id' => 'TR0011large123',
            'token' => 'test_token',
            'amount' => '500.00',
            'invoice_number' => 'INV010',
            'cart_id' => 501,
            'status' => PaymentStatus::SUCCESS->value,
            'meta' => $largeMeta,
        ]);

        $this->assertIsArray($payment->meta);
        $this->assertEquals($largeMeta, $payment->meta);
        $this->assertEquals('TR0011large123', $payment->meta['payment_details']['paymentID']);
    }
}
