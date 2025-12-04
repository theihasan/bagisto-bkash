<?php

namespace Ihasan\Bkash\DTO;

readonly class PaymentCreateResponseDTO extends BaseDTO
{
    public function __construct(
        public string $paymentID,
        public string $bkashURL,
        public string $callbackURL,
        public string $successCallbackURL,
        public string $failureCallbackURL,
        public string $cancelledCallbackURL,
        public string $amount,
        public string $intent,
        public string $currency,
        public string $paymentCreateTime,
        public string $transactionStatus,
        public string $merchantInvoiceNumber,
        public string $statusCode,
        public string $statusMessage
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            paymentID: $data['paymentID'] ?? '',
            bkashURL: $data['bkashURL'] ?? '',
            callbackURL: $data['callbackURL'] ?? '',
            successCallbackURL: $data['successCallbackURL'] ?? '',
            failureCallbackURL: $data['failureCallbackURL'] ?? '',
            cancelledCallbackURL: $data['cancelledCallbackURL'] ?? '',
            amount: $data['amount'] ?? '0.00',
            intent: $data['intent'] ?? 'sale',
            currency: $data['currency'] ?? 'BDT',
            paymentCreateTime: $data['paymentCreateTime'] ?? '',
            transactionStatus: $data['transactionStatus'] ?? '',
            merchantInvoiceNumber: $data['merchantInvoiceNumber'] ?? '',
            statusCode: $data['statusCode'] ?? '',
            statusMessage: $data['statusMessage'] ?? ''
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === '0000' && !empty($this->paymentID);
    }
}