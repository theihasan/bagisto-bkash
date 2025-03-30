<?php

namespace Webkul\BkashPayment\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webkul\BkashPayment\Services\BkashPaymentService;
use Webkul\BkashPayment\Exceptions\PaymentException;
use Webkul\Payment\Payment\Payment;
use Webkul\Checkout\Facades\Cart;

class BkashPayment extends Payment
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $code = 'bkash_payment';

    /**
     * Create a new payment method instance.
     *
     * @param BkashPaymentService $bkashPaymentService
     * @return void
     */
    public function __construct(
        protected BkashPaymentService $bkashPaymentService
    ) {
    }

    /**
     * Get redirect URL
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        try {
            $cart = Cart::getCart();
            $paymentData = $this->bkashPaymentService->createPayment($cart);

            Log::info('bKash payment initiated:', $paymentData);

            return $paymentData['bkashURL'];
        } catch (PaymentException $e) {
            Log::error('bKash payment initiation error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            session()->flash('error', $e->getMessage());
            return route('shop.checkout.cart.index');
        } catch (\Exception $e) {
            Log::error('Unexpected error in bKash payment initiation:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            session()->flash('error', 'An error occurred while setting up the payment. Please try again or contact support.');
            return route('shop.checkout.cart.index');
        }
    }

    /**
     * Process the payment callback
     *
     * @param Request $request
     * @return mixed
     */
    public function handleCallback(Request $request)
    {
        return $this->bkashPaymentService->processCallback($request);
    }

    /**
     * Get payment method image
     *
     * @return string
     */
    public function getImage(): string
    {
        $url = $this->getConfigData('image');
        return $url ? Storage::url($url) : '';
    }
}
