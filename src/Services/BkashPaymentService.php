<?php

namespace Webkul\BkashPayment\Services;

use Ihasan\Bkash\Facades\Bkash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\BkashPayment\Exceptions\ConfigurationException;
use Webkul\BkashPayment\Exceptions\PaymentException;
use Webkul\BkashPayment\Exceptions\PaymentFailedException;
use Webkul\BkashPayment\Models\BkashTransaction;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderPayment;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;

class BkashPaymentService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository,
        protected BkashConfigManager $configManager
    ) {}

    /**
     * Create bKash payment
     *
     * @param  mixed  $cart
     *
     * @throws ConfigurationException
     * @throws PaymentException
     */
    public function createPayment($cart): array
    {
        try {
            $this->configManager->validateConfiguration();

            $paymentData = [
                'amount'                  => number_format($cart->grand_total, 2, '.', ''),
                'currency'                => 'BDT',
                'intent'                  => 'sale',
                'payer_reference'         => $cart->customer_email ?? 'guest-'.$cart->id,
                'merchant_invoice_number' => 'INV-'.$cart->id.'-'.time(),
                'callback_url'            => route('bkash.callback'),
            ];

            try {
                $response = Bkash::createPayment($paymentData);
                $this->saveTransaction($response, $cart->id);

                return [
                    'bkashURL'  => $response['bkashURL'],
                    'paymentID' => $response['paymentID'],
                ];
            } catch (\Ihasan\Bkash\Exceptions\PaymentCreateException $e) {
                Log::error('bKash payment creation error: '.$e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new PaymentException('Failed to create bKash payment: '.$e->getMessage());
            }
        } catch (ConfigurationException $e) {
            Log::error('bKash configuration error: '.$e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('bKash payment creation error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw new PaymentException('Failed to create bKash payment: '.$e->getMessage());
        }
    }

    /**
     * Execute bKash payment
     *
     * @throws PaymentFailedException
     */
    public function executePayment(string $paymentId): array
    {
        try {
            $response = Bkash::executePayment($paymentId);

            $this->updateTransaction($paymentId, $response);

            return $response;
        } catch (\Ihasan\Bkash\Exceptions\PaymentExecuteException $e) {
            Log::error('bKash payment execution error: '.$e->getMessage(), [
                'paymentId' => $paymentId,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw new PaymentFailedException('Failed to execute bKash payment: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('bKash payment execution error: '.$e->getMessage(), [
                'paymentId' => $paymentId,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw new PaymentFailedException('Failed to execute bKash payment: '.$e->getMessage());
        }
    }

    /**
     * Query payment status
     */
    public function queryPayment(string $paymentId): array
    {
        try {
            return Bkash::queryPayment($paymentId);
        } catch (\Ihasan\Bkash\Exceptions\PaymentQueryException $e) {
            Log::error('bKash payment query error: '.$e->getMessage(), [
                'paymentId' => $paymentId,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw new PaymentException('Failed to query bKash payment: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('bKash payment query error: '.$e->getMessage(), [
                'paymentId' => $paymentId,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw new PaymentException('Failed to query bKash payment: '.$e->getMessage());
        }
    }

    /**
     * Process payment callback
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processCallback(Request $request)
    {
        Log::info('bKash callback received', $request->all());

        $paymentID = $request->input('paymentID');
        $status = $request->input('status');

        if (! $paymentID) {
            Log::error('Missing payment ID in bKash callback');

            return redirect()->route('shop.checkout.cart.index')
                ->with('error', 'Payment information is missing.');
        }

        try {
            $transaction = BkashTransaction::where('payment_id', $paymentID)
                ->where('status', 'pending')
                ->firstOrFail();

            if ($status === 'success') {
                return $this->handleSuccessCallback($paymentID, $transaction);
            } else {
                return $this->handleFailedCallback($paymentID, $transaction, $status);
            }
        } catch (\Exception $e) {
            Log::error('Error processing bKash callback: '.$e->getMessage(), [
                'paymentId' => $paymentID,
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()->route('shop.checkout.cart.index')
                ->with('error', 'Payment processing failed: '.$e->getMessage());
        }
    }

    /**
     * Handle successful callback
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleSuccessCallback(string $paymentId, BkashTransaction $transaction)
    {
        return DB::transaction(function () use ($paymentId, $transaction) {
            try {
                $paymentResponse = $this->executePayment($paymentId);

                if (($paymentResponse['statusCode'] ?? '') !== '0000' || ($paymentResponse['transactionStatus'] ?? '') !== 'Completed') {
                    $transaction->update([
                        'status'        => 'failed',
                        'response_data' => json_encode($paymentResponse),
                    ]);

                    throw new PaymentFailedException('Payment verification failed. Status: '.
                        ($paymentResponse['transactionStatus'] ?? 'Unknown'));
                }

                $cart = $this->loadCart($transaction->cart_id);

                $order = $this->createOrder($cart);

                // Update transaction with order info
                $transaction->update([
                    'status'         => 'completed',
                    'order_id'       => $order->id,
                    'transaction_id' => $paymentResponse['trxID'] ?? null,
                    'response_data'  => json_encode($paymentResponse),
                ]);

                $this->updateOrderPayment($order, $paymentId, $paymentResponse['trxID'] ?? null);

                $this->createInvoiceIfPossible($order);

                Cart::deActivateCart();

                session()->put('order', $order);

                Log::info('bKash payment completed successfully', [
                    'order_id'   => $order->id,
                    'payment_id' => $paymentId,
                ]);

                $redirectUrl = core()->getConfigData('sales.payment_methods.bkash_payment.success_url') ?: route('shop.checkout.onepage.success');

                return redirect()->to($redirectUrl);

            } catch (PaymentFailedException $e) {
                $transaction->update([
                    'status'        => 'failed',
                    'response_data' => json_encode(['error' => $e->getMessage()]),
                ]);

                $redirectUrl = core()->getConfigData('sales.payment_methods.bkash_payment.fail_url') ?: route('shop.checkout.cart.index');

                return redirect()->to($redirectUrl)->with('error', $e->getMessage());
            }
        });
    }

    /**
     * Handle failed callback
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleFailedCallback(string $paymentId, BkashTransaction $transaction, string $status)
    {
        $transaction->update([
            'status'        => $status,
            'response_data' => json_encode(['status' => $status, 'payment_id' => $paymentId]),
        ]);

        Log::info('bKash payment failed or cancelled', [
            'payment_id' => $paymentId,
            'status'     => $status,
        ]);

        $redirectUrl = core()->getConfigData('sales.payment_methods.bkash_payment.fail_url') ?: route('shop.checkout.cart.index');

        return redirect()->to($redirectUrl)
            ->with('error', 'Payment was not completed. Status: '.ucfirst($status));
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $paymentId, string $trxId, float $amount, string $reason = 'Customer requested refund'): array
    {
        try {
            $refundData = [
                'paymentID' => $paymentId,
                'trxID'     => $trxId,
                'amount'    => number_format($amount, 2, '.', ''),
                'reason'    => $reason,
            ];
            $response = Bkash::refundPayment($refundData);

            $this->recordRefund($paymentId, $trxId, $amount, $response);

            return $response;
        } catch (\Ihasan\Bkash\Exceptions\RefundException $e) {
            Log::error('bKash refund error: '.$e->getMessage(), [
                'paymentId' => $paymentId,
                'trxId'     => $trxId,
                'amount'    => $amount,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw new PaymentException('Failed to refund bKash payment: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('bKash refund error: '.$e->getMessage(), [
                'paymentId' => $paymentId,
                'trxId'     => $trxId,
                'amount'    => $amount,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw new PaymentException('Failed to refund bKash payment: '.$e->getMessage());
        }
    }

    /**
     * Save transaction to database
     */
    protected function saveTransaction(array $paymentData, int $cartId): void
    {
        BkashTransaction::create([
            'payment_id'    => $paymentData['paymentID'],
            'cart_id'       => $cartId,
            'amount'        => $paymentData['amount'] ?? 0,
            'invoice_id'    => $paymentData['merchantInvoiceNumber'] ?? null,
            'status'        => 'pending',
            'response_data' => json_encode($paymentData),
        ]);
    }

    /**
     * Update transaction with payment data
     */
    protected function updateTransaction(string $paymentId, array $paymentData): void
    {
        $transaction = BkashTransaction::where('payment_id', $paymentId)->first();

        if ($transaction) {
            $status = isset($paymentData['trxID']) && ($paymentData['transactionStatus'] ?? '') === 'Completed' ? 'completed' : 'failed';

            $transaction->update([
                'status'         => $status,
                'transaction_id' => $paymentData['trxID'] ?? null,
                'response_data'  => json_encode($paymentData),
            ]);
        }
    }

    /**
     * Record refund in the database
     */
    protected function recordRefund(string $paymentId, string $trxId, float $amount, array $refundData): void
    {
        $transaction = BkashTransaction::where('payment_id', $paymentId)
            ->where('transaction_id', $trxId)
            ->first();

        if ($transaction) {
            $transaction->update([
                'refund_amount' => $amount,
                'refund_data'   => json_encode($refundData),
                'status'        => 'refunded',
            ]);
        }
    }

    /**
     * Load and validate cart
     *
     * @return \Webkul\Checkout\Models\Cart
     *
     * @throws \Exception
     */
    protected function loadCart(int $cartId)
    {
        $cart = \Webkul\Checkout\Models\Cart::find($cartId);

        if (! $cart) {
            throw new \Exception('Cart not found. Please contact support.');
        }

        Cart::setCart($cart);

        return $cart;
    }

    /**
     * Create order from cart
     *
     * @param  \Webkul\Checkout\Models\Cart  $cart
     * @return \Webkul\Sales\Models\Order
     */
    protected function createOrder($cart)
    {
        $data = (new OrderResource($cart))->jsonSerialize();

        return $this->orderRepository->create($data);
    }

    /**
     * Update order payment with transaction ID
     */
    protected function updateOrderPayment(Order $order, string $paymentId, ?string $transactionId): void
    {
        OrderPayment::where('order_id', $order->id)->update([
            'additional' => json_encode([
                'payment_id'     => $paymentId,
                'transaction_id' => $transactionId,
            ]),
        ]);
    }

    /**
     * Create invoice if possible
     */
    protected function createInvoiceIfPossible(Order $order): void
    {
        if ($order->canInvoice()) {
            $this->invoiceRepository->create($this->prepareInvoiceData($order));
        }
    }

    /**
     * Prepare invoice data
     */
    protected function prepareInvoiceData(Order $order): array
    {
        $invoiceData = ['order_id' => $order->id];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }
}
