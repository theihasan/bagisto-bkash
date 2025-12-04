<?php

namespace Ihasan\Bkash\DTO;

readonly class PaymentCreateRequestDTO extends BaseDTO
{
    public function __construct(
        public string $mode,
        public string $payerReference,
        public string $callbackURL,
        public string $amount,
        public string $currency,
        public string $intent,
        public string $merchantInvoiceNumber
    ) {}

    public static function fromCart(object $cart): static
    {
        return new static(
            mode: '0011',
            payerReference: $cart->customer_email ?? 'guest',
            callbackURL: config('app.url') . '/bkash/callback',
            amount: number_format($cart->grand_total, 2, '.', ''),
            currency: 'BDT',
            intent: 'sale',
            merchantInvoiceNumber: 'INV' . $cart->id
        );
    }
}