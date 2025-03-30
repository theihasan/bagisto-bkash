<?php


use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Webkul\BkashPayment\Payment\BkashPayment;

class BkashPaymentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param BkashPayment $bkashPayment
     * @return void
     */
    public function __construct(
        protected BkashPayment $bkashPayment
    ) {
    }

    /**
     * Handle bKash payment callback
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function callback(Request $request)
    {
        return $this->bkashPayment->handleCallback($request);
    }

    /**
     * Handle success redirection
     *
     * @return \Illuminate\Http\Response
     */
    public function success()
    {
        return \Webkul\BkashPayment\Http\Controllers\redirect()->route('shop.checkout.onepage.success');
    }

    /**
     * Handle failure redirection
     *
     * @return \Illuminate\Http\Response
     */
    public function fail()
    {
        return \Webkul\BkashPayment\Http\Controllers\redirect()->route('shop.checkout.cart.index')
            ->with('error', 'Payment failed or was cancelled. Please try again.');
    }

    /**
     * Handle cancellation redirection
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel()
    {
        return \Webkul\BkashPayment\Http\Controllers\redirect()->route('shop.checkout.cart.index')
            ->with('error', 'Payment was cancelled. Please try again.');
    }
}
