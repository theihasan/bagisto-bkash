<?php

namespace Ihasan\Bkash;

use Ihasan\Bkash\Contracts\BkashPayment as BkashPaymentContract;
use Ihasan\Bkash\Services\BkashPaymentService;

class Bkash implements BkashPaymentContract
{
    public function __construct(protected BkashPaymentService $paymentService)
    {
        //
    }

    /**
     * Create a payment
     */
    public function createPayment($cart): array
    {
        return $this->paymentService->createPayment($cart);
    }

    /**
     * Execute a payment
     */
    public function executePayment(string $paymentId): array
    {
        return $this->paymentService->executePayment($paymentId);
    }

    /**
     * Process callback
     */
    public function processCallback($request)
    {
        return $this->paymentService->processCallback($request);
    }

    /**
     * Get payment credentials
     */
    public function getCredentials(): array
    {
        return $this->paymentService->getCredentials();
    }

    /**
     * Get token
     */
    public function getToken(): string
    {
        return $this->paymentService->getToken();
    }
}
