<?php

namespace Ihasan\Bkash\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ihasan\Bkash\DTO\BkashCredentialsDTO;
use Ihasan\Bkash\DTO\PaymentCreateRequestDTO;
use Ihasan\Bkash\DTO\PaymentCreateResponseDTO;
use Ihasan\Bkash\DTO\PaymentExecuteResponseDTO;
use Ihasan\Bkash\DTO\PaymentQueryResponseDTO;
use Ihasan\Bkash\DTO\TokenResponseDTO;
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
     */
    public function getCredentials(): BkashCredentialsDTO
    {
        $credentials = BkashCredentialsDTO::fromConfig();
        
        if (!$credentials->isValid()) {
            $missing = $credentials->validate();
            throw new ConfigurationException('Missing bkash configuration: ' . implode(', ', $missing));
        }

        return $credentials;
    }

    /**
     * Get authorization token from bKash API (with caching)
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
            $tokenResponse = $this->makeTokenRequest($credentials);

            cache()->put($cacheKey, $tokenResponse->idToken, now()->addSeconds($tokenResponse->expiresIn));

            return $tokenResponse->idToken;
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
    private function makeTokenRequest(BkashCredentialsDTO $credentials): TokenResponseDTO
    {
        $response = Http::bkash()
            ->withHeaders([
                'username' => $credentials->username,
                'password' => $credentials->password,
            ])
            ->post('/checkout/token/grant', [
                'app_key'    => $credentials->appKey,
                'app_secret' => $credentials->appSecret,
            ]);

        $tokenResponse = TokenResponseDTO::fromArray($response->json());
        
        if (!$response->successful() || !$tokenResponse->isSuccessful()) {
            throw new TokenException(
                'Failed to get bkash token: ' . $tokenResponse->statusMessage
            );
        }

        return $tokenResponse;
    }

    /**
     * Create a new bKash payment
     */
    public function createPayment($cart): PaymentCreateResponseDTO
    {
        try {
            $credentials = $this->getCredentials();
            $token = $this->getToken();

            $request = PaymentCreateRequestDTO::fromCart($cart);
            $response = $this->sendPaymentRequest($token, $credentials->appKey, $request);

            $this->savePaymentRecord($response, $token, $request, $cart->id);

            return $response;
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
     * Send payment creation request to bKash
     */
    private function sendPaymentRequest(string $token, string $appKey, PaymentCreateRequestDTO $request): PaymentCreateResponseDTO
    {
        $response = Http::bkashWithToken($token, $appKey)
            ->post('/checkout/payment/create', $request->toArray());

        $paymentResponse = PaymentCreateResponseDTO::fromArray($response->json());

        if (!$response->successful() || !$paymentResponse->isSuccessful()) {
            throw new PaymentCreationException(
                'Failed to create bkash payment: ' . $paymentResponse->statusMessage
            );
        }

        return $paymentResponse;
    }

    /**
     * Save the payment record to database
     */
    private function savePaymentRecord(
        PaymentCreateResponseDTO $paymentResponse, 
        string $token, 
        PaymentCreateRequestDTO $request, 
        int $cartId
    ): void {
        $status = match ($paymentResponse->transactionStatus) {
            'Initiated' => PaymentStatus::INITIATED->value,
            'Completed' => PaymentStatus::COMPLETED->value,
            'Failed'    => PaymentStatus::FAILED->value,
            'Cancelled' => PaymentStatus::CANCELLED->value,
            default     => PaymentStatus::PENDING->value
        };

        BkashPayment::create([
            'payment_id'     => $paymentResponse->paymentID,
            'token'          => $token,
            'amount'         => $request->amount,
            'invoice_number' => $request->merchantInvoiceNumber,
            'cart_id'        => $cartId,
            'status'         => $status,
            'meta'           => $paymentResponse->toJson(),
        ]);
    }

    /**
     * Execute a bKash payment
     */
    public function executePayment(string $paymentId): PaymentExecuteResponseDTO
    {
        $credentials = $this->getCredentials();
        $token = $this->getToken();

        Log::info('bKash execute payment:', [
            'paymentID' => $paymentId,
            'base_url'  => $credentials->baseUrl,
        ]);

        $response = Http::bkashWithToken($token, $credentials->appKey)
            ->timeout(30)
            ->post("/checkout/payment/execute/{$paymentId}");

        Log::debug('bKash execute response:', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        $executeResponse = PaymentExecuteResponseDTO::fromArray($response->json());

        if (!$response->successful() || !$executeResponse->isSuccessful()) {
            throw new PaymentCreationException(
                'Payment execution failed: ' . $executeResponse->statusMessage
            );
        }

        return $executeResponse;
    }

    /**
     * Query payment status
     */
    public function queryPayment(string $paymentId): PaymentQueryResponseDTO
    {
        $credentials = $this->getCredentials();
        $token = $this->getToken();

        $response = Http::bkashWithToken($token, $credentials->appKey)
            ->timeout(30)
            ->post("/checkout/payment/query/{$paymentId}");

        $queryResponse = PaymentQueryResponseDTO::fromArray($response->json());

        if (!$response->successful() || !$queryResponse->isSuccessful()) {
            throw new PaymentCreationException(
                'Payment query failed: ' . $queryResponse->statusMessage
            );
        }

        return $queryResponse;
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

            $executeResponse = $this->executePayment($paymentId);

            return $this->processSuccessfulPayment($bkashPayment, $paymentId, $executeResponse);

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
    private function findPaymentRecord(string $paymentId): BkashPayment
    {
        return BkashPayment::where('payment_id', $paymentId)
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

        if (!$cart) {
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
    private function processSuccessfulPayment(
        BkashPayment $bkashPayment, 
        string $paymentId, 
        PaymentExecuteResponseDTO $executeResponse
    ) {
        return DB::transaction(function () use ($bkashPayment, $paymentId, $executeResponse) {
            $bkashPayment->update([
                'status' => PaymentStatus::SUCCESS->value,
                'meta'   => $executeResponse->toJson(),
            ]);

            // Create order
            $order = $this->createOrder();

            $this->savePaymentTransactionId($order->id, $executeResponse->trxID ?: $paymentId);
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