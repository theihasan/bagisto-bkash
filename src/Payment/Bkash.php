<?php

namespace Ihasan\Bkash\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ihasan\Bkash\DTO\PaymentCreateResponseDTO;
use Ihasan\Bkash\Services\BkashPaymentService;
use Webkul\Payment\Payment\Payment;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Ihasan\Bkash\Exceptions\PaymentCreationException;

class Bkash extends Payment
{
    protected $code = 'bkash';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository,
        protected BkashPaymentService $bkashPaymentService
    ) {}

    /**
     * @throws PaymentCreationException
     */
    protected function createPayment($cart): PaymentCreateResponseDTO
    {
        return $this->bkashPaymentService->createPayment($cart);
    }

    public function handleCallback(Request $request)
    {
        return $this->bkashPaymentService->processCallback($request);
    }

    /**
     * Get redirect url
     */
    public function getRedirectUrl()
    {
        try {
            $cart = cart()->getCart();
            $paymentResponse = $this->createPayment($cart);

            Log::debug('bkash Payment Created:', $paymentResponse->toArray());

            return $paymentResponse->bkashURL;
        } catch (\Exception $e) {
            Log::error('bkash Redirect URL Exception:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            session()->flash('error', $e->getMessage());

            return route('shop.checkout.cart.index');
        }
    }

    public function getImage(): string
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : asset('img/payment_banner.png');
    }
}