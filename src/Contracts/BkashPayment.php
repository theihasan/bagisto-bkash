<?php

namespace Ihasan\Bkash\Contracts;

interface BkashPayment
{
    /**
     * Create a payment
     */
    public function createPayment($cart): array;

    /**
     * Execute a payment
     */
    public function executePayment(string $paymentId): array;

    /**
     * Process callback
     */
    public function processCallback($request);
}