<?php

namespace Ihasan\Bkash\DTO;

readonly class PaymentExecuteResponseDTO extends BaseDTO
{
    public function __construct(
        public string $paymentID,
        public string $trxID,
        public string $transactionStatus,
        public string $amount,
        public string $currency,
        public string $intent,
        public string $paymentExecuteTime,
        public string $merchantInvoiceNumber,
        public string $payerType,
        public string $payerReference,
        public string $customerMsisdn,
        public string $payerAccount,
        public string $maxRefundableAmount,
        public string $statusCode,
        public string $statusMessage
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            paymentID: $data['paymentID'] ?? '',
            trxID: $data['trxID'] ?? '',
            transactionStatus: $data['transactionStatus'] ?? '',
            amount: $data['amount'] ?? '0.00',
            currency: $data['currency'] ?? 'BDT',
            intent: $data['intent'] ?? 'sale',
            paymentExecuteTime: $data['paymentExecuteTime'] ?? '',
            merchantInvoiceNumber: $data['merchantInvoiceNumber'] ?? '',
            payerType: $data['payerType'] ?? '',
            payerReference: $data['payerReference'] ?? '',
            customerMsisdn: $data['customerMsisdn'] ?? '',
            payerAccount: $data['payerAccount'] ?? '',
            maxRefundableAmount: $data['maxRefundableAmount'] ?? '0.00',
            statusCode: $data['statusCode'] ?? '',
            statusMessage: $data['statusMessage'] ?? ''
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === '0000' && $this->transactionStatus === 'Completed';
    }
}