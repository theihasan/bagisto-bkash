<?php

namespace Ihasan\Bkash\DTO;

readonly class PaymentQueryResponseDTO extends BaseDTO
{
    public function __construct(
        public string $paymentID,
        public string $mode,
        public string $paymentCreateTime,
        public string $amount,
        public string $currency,
        public string $intent,
        public string $merchantInvoice,
        public string $transactionStatus,
        public string $maxRefundableAmount,
        public string $verificationStatus,
        public string $payerReference,
        public string $payerType,
        public string $payerAccount,
        public string $statusCode,
        public string $statusMessage,
        public ?string $trxID = null,
        public ?string $paymentExecuteTime = null
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            paymentID: $data['paymentID'] ?? '',
            mode: $data['mode'] ?? '',
            paymentCreateTime: $data['paymentCreateTime'] ?? '',
            amount: $data['amount'] ?? '0.00',
            currency: $data['currency'] ?? 'BDT',
            intent: $data['intent'] ?? 'sale',
            merchantInvoice: $data['merchantInvoice'] ?? '',
            transactionStatus: $data['transactionStatus'] ?? '',
            maxRefundableAmount: $data['maxRefundableAmount'] ?? '0.00',
            verificationStatus: $data['verificationStatus'] ?? '',
            payerReference: $data['payerReference'] ?? '',
            payerType: $data['payerType'] ?? '',
            payerAccount: $data['payerAccount'] ?? '',
            statusCode: $data['statusCode'] ?? '',
            statusMessage: $data['statusMessage'] ?? '',
            trxID: $data['trxID'] ?? null,
            paymentExecuteTime: $data['paymentExecuteTime'] ?? null
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === '0000';
    }

    public function isCompleted(): bool
    {
        return $this->transactionStatus === 'Completed';
    }
}