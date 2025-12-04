<?php

namespace Ihasan\Bkash\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ihasan\Bkash\Models\BkashPayment;
use Ihasan\Bkash\PaymentStatus;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Models\OrderPayment;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;
use Ihasan\Bkash\Exceptions\ConfigurationException;
use Ihasan\Bkash\Exceptions\PaymentCreationException;
use Ihasan\Bkash\Exceptions\TokenException;

class BkashPaymentService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository
    ) {}

    /**
     * Get bKash API credentials from configuration
     *
     * @throws ConfigurationException
     */
    public function getCredentials(): array
    {
        $sandbox = core()->getConfigData('sales.payment_methods.bkash.bkash_sandbox');

        $credentials = [
            'username'   => core()->getConfigData('sales.payment_methods.bkash.bkash_username'),
            'password'   => core()->getConfigData('sales.payment_methods.bkash.bkash_password'),
            'app_key'    => core()->getConfigData('sales.payment_methods.bkash.bkash_app_key'),
            'app_secret' => core()->getConfigData('sales.payment_methods.bkash.bkash_app_secret'),
            'base_url'   => $sandbox === '1'
                ? core()->getConfigData('sales.payment_methods.bkash.sandbox_base_url')
                : core()->getConfigData('sales.payment_methods.bkash.live_base_url'),
            'sandbox'    => $sandbox === '1' || $sandbox === true,
        ];

        $this->validateCredentials($credentials);

        return $credentials;
    }

    /**
     * Validate that all required credentials are present
     */
    private function validateCredentials(array $credentials): void
    {
        $requiredKeys = ['username', 'password', 'app_key', 'app_secret', 'base_url'];

        foreach ($requiredKeys as $key) {
            if (empty($credentials[$key])) {
                throw new ConfigurationException("Missing bkash configuration: {$key}");
            }
        }
    }

    /**
     * Get authorization token from bKash API (with caching)
     *
     * @throws TokenException
     */
    public function getToken(): string
    {
        $cacheKey = 'bkash_token';

        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        return $this->fetchAndCacheToken($cacheKey);
    }

    /**
     * Fetch a new token from bKash API and cache it
     */
    private function fetchAndCacheToken(string $cacheKey): string
    {
        try {
            $credentials = $this->getCredentials();
            $response = $this->makeTokenRequest($credentials);

            $data = $response->json();
            $token = $data['id_token'] ?? $data['token'] ?? $data['access_token'] ?? null;

            if (! $token) {
                throw new TokenException('Token not found in bkash response');
            }

            $expiresIn = $data['expires_in'] ?? 3600;
            cache()->put($cacheKey, $token, now()->addSeconds($expiresIn));

            return $token;
        } catch (\Exception $e) {
            Log::error('bkash Token Exception:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Make API request to get token
     */
    private function makeTokenRequest(array $credentials)
    {
        $response = Http::bkash()
            ->withHeaders([
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ])
            ->post('/tokenized/checkout/token/grant', [
                'app_key'    => $credentials['app_key'],
                'app_secret' => $credentials['app_secret'],
            ]);

        if (! isset($response->json()['id_token'])) {
            throw new TokenException(
                'Failed to get bkash token: '.
                ($response->json('message') ?? 'Unknown error')
            );
        }

        return $response;
    }

    /**
     * Create a new bKash payment
     */
    public function createPayment($cart): array
    {
        try {
            $credentials = $this->getCredentials();
            $token = $this->getToken();

            $payload = $this->buildPaymentPayload($cart);
            $paymentData = $this->sendPaymentRequest($token, $credentials['app_key'], $payload);

            $this->savePaymentRecord($paymentData, $token, $payload, $cart->id);

            return $paymentData;
        } catch (\Exception $e) {
            Log::error('bkash Create Payment Exception:', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            throw $e instanceof PaymentCreationException
                ? $e
                : new PaymentCreationException('Failed to create bkash payment: '.$e->getMessage());
        }
    }

    /**
     * Build the payment request payload
     */
    private function buildPaymentPayload($cart): array
    {
        return [
            'mode'                  => '0011',
            'payerReference'        => $cart->customer_email ?? 'guest',
            'callbackURL'           => route('bkash.callback'),
            'amount'                => number_format($cart->grand_total, 2, '.', ''),
            'currency'              => 'BDT',
            'intent'                => 'sale',
            'merchantInvoiceNumber' => 'INV'.$cart->id,
        ];
    }

    /**
     * Send payment creation request to bKash
     */
    private function sendPaymentRequest(string $token, string $appKey, array $payload): array
    {
        $response = Http::bkashWithToken($token, $appKey)
            ->post('/tokenized/checkout/create', $payload);

        if (! $response->successful()) {
            throw new PaymentCreationException(
                'Failed to create bkash payment: '.
                ($response->json()['statusMessage'] ?? 'Unknown error')
            );
        }

        $paymentData = $response->json();

        if ($paymentData['statusCode'] !== '0000') {
            throw new PaymentCreationException(
                'bkash error: '.
                ($paymentData['statusMessage'] ?? 'Unknown error')
            );
        }

        return $paymentData;
    }

    /**
     * Save the payment record to database
     */
    private function savePaymentRecord(array $paymentData, string $token, array $payload, int $cartId): void
    {
        $status = match ($paymentData['transactionStatus']) {
            'Initiated' => PaymentStatus::INITIATED->value,
            'Completed' => PaymentStatus::COMPLETED,
            'Failed'    => PaymentStatus::FAILED->value,
            'Cancelled' => PaymentStatus::CANCELLED->value,
            default     => PaymentStatus::PENDING->value
        };

        BkashPayment::query()->create([
            'payment_id'     => $paymentData['paymentID'],
            'token'          => $token,
            'amount'         => $payload['amount'],
            'invoice_number' => $payload['merchantInvoiceNumber'],
            'cart_id'        => $cartId,
            'status'         => $status,
            'meta'           => json_encode($paymentData),
        ]);
    }

    /**
     * Execute a bKash payment
     */
    public function executePayment(string $paymentId): array
    {
        $credentials = $this->getCredentials();
        $token = $this->getToken();

        Log::info('bKash execute request:', [
            'paymentID'    => $paymentId,
            'token_length' => strlen($token),
            'app_key'      => $credentials['app_key'],
        ]);

        $response = $this->sendExecuteRequest($token, $credentials['app_key'], $paymentId);

        Log::debug('bKash execute response:', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        $this->validateExecuteResponse($response);

        return $response->json();
    }

    /**
     * Send payment execution request to bKash
     */
    private function sendExecuteRequest(string $token, string $appKey, string $paymentId)
    {
        return Http::bkashWithToken($token, trim($appKey))
            ->post('/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);
    }

    /**
     * Validate the execute response
     */
    private function validateExecuteResponse($response)
    {
        if (! $response->successful()) {
            throw new PaymentCreationException('Payment execution failed: '.
                ($response->json()['statusMessage'] ?? 'Unknown error'));
        }

        $payment = $response->json();
        if ($payment['statusCode'] !== '0000') {
            throw new PaymentCreationException('Payment execution failed: '.
                ($payment['statusMessage'] ?? 'Unknown error'));
        }
    }

    /**
     * Process bKash callback and create order
     */
    public function processCallback(Request $request)
    {
        Log::debug('bKash callback received:', $request->all());

        try {
            $paymentId = $request->paymentID;
            $paymentStatus = $request->status;

            $bkashPayment = $this->findPaymentRecord($paymentId);

            if ($paymentStatus !== 'success') {
                $bkashPayment->update([
                    'status' => $paymentStatus,
                    'meta'   => json_encode($request->all()),
                ]);

                session()->flash('error', 'Payment was cancelled. Please try again.');

                return redirect()->route('shop.checkout.cart.index');
            }

            $cart = $this->loadCart($bkashPayment->cart_id, $paymentId);

            $payment = $this->executePayment($paymentId);

            return $this->processSuccessfulPayment($bkashPayment, $paymentId, $payment);

        } catch (PaymentCreationException $e) {
            Log::error('Payment Creation Error: '.$e->getMessage());
            session()->flash('error', $e->getMessage());

            return redirect()->route('shop.checkout.cart.index');
        } catch (\Exception $e) {
            Log::error('Callback Processing Error: '.$e->getMessage());
            session()->flash('error', 'Payment processing failed. Please try again.');

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    /**
     * Find the payment record
     */
    private function findPaymentRecord(string $paymentId)
    {
        return BkashPayment::query()
            ->where('payment_id', $paymentId)
            ->whereIn('status', [
                PaymentStatus::PENDING->value,
                PaymentStatus::INITIATED->value,
            ])
            ->firstOrFail();
    }

    /**
     * Load and validate cart
     */
    private function loadCart(int $cartId, string $paymentId)
    {
        $cart = \Webkul\Checkout\Models\Cart::find($cartId);

        Log::debug('Cart found during callback:', [
            'cart_id' => $cartId,
            'exists'  => (bool) $cart,
        ]);

        if (! $cart) {
            Log::error('Cart not found during bKash callback', [
                'payment_id' => $paymentId,
                'cart_id'    => $cartId,
            ]);
            throw new \Exception('Cart not found. Please contact support.');
        }

        Cart::setCart($cart);

        return $cart;
    }

    /**
     * Process successful payment and create order
     */
    private function processSuccessfulPayment(BkashPayment $bkashPayment, string $paymentId, array $payment)
    {
        return DB::transaction(function () use ($bkashPayment, $paymentId, $payment) {
            $bkashPayment->update([
                'status'         => PaymentStatus::SUCCESS->value,
                'meta'           => json_encode($payment),
            ]);

            // Create order
            $order = $this->createOrder();

            $this->savePaymentTransactionId($order->id, $payment['trxID'] ?? $paymentId);
            $this->createInvoiceIfPossible($order);
            session()->put('order_id', $order->id);
            // Cleanup
            Cart::deActivateCart();
            session()->flash('order', $order);

            Log::debug('bKash payment completed successfully', [
                'order_id'   => $order->id,
                'payment_id' => $paymentId,
            ]);

            return redirect()->route('shop.checkout.onepage.success');
        });
    }

    /**
     * Create order from cart
     */
    private function createOrder()
    {
        $data = (new OrderResource(Cart::getCart()))->jsonSerialize();

        return $this->orderRepository->create($data);
    }

    /**
     * Create invoice if possible
     */
    private function createInvoiceIfPossible($order): void
    {
        if ($order->canInvoice()) {
            $this->invoiceRepository->create($this->prepareInvoiceData($order));
        }
    }

    /**
     * Handle processing error
     */
    private function handleProcessingError(\Exception $e, string $paymentId, int $cartId)
    {
        Log::error('Error creating order after bKash payment', [
            'payment_id' => $paymentId,
            'cart_id'    => $cartId,
            'error'      => $e->getMessage(),
            'trace'      => $e->getTraceAsString(),
        ]);

        session()->flash('error', 'Payment processing failed. Please contact support.');

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Prepare invoice data for the order
     */
    protected function prepareInvoiceData($order): array
    {
        $invoiceData = [
            'order_id' => $order->id,
            'invoice'  => ['items' => []],
        ];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }

    /**
     * Save payment transaction ID for the order
     */
    protected function savePaymentTransactionId(int $orderId, string $transactionId): void
    {
        $jsonData = json_encode([
            'transaction_id' => $transactionId,
            'payment_method' => 'bkash',
            'status'         => 'completed',
            'timestamp'      => now()->toIso8601String(),
        ]);

        OrderPayment::where('order_id', $orderId)->update([
            'additional' => $jsonData,
        ]);
    }
}