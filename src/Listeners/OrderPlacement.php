<?php

namespace Webkul\BkashPayment\Listeners;

use Illuminate\Support\Facades\Log;
use Webkul\BkashPayment\Models\BkashTransaction;
use Webkul\Sales\Models\Order;

class OrderPlacement
{
    /**
     * Handle the event.
     *
     * @param  \Webkul\Sales\Models\Order  $order
     * @return void
     */
    public function handle(Order $order)
    {
        if ($order->payment->method !== 'bkash_payment') {
            return;
        }

        try {
            // Check for any additional data we might want to store or process
            $additionalData = json_decode($order->payment->additional, true);
            
            if (empty($additionalData) || empty($additionalData['payment_id'])) {
                return;
            }
            
            $paymentId = $additionalData['payment_id'];
            
            // Update any pending transaction that might exist
            $transaction = BkashTransaction::where('payment_id', $paymentId)
                ->where('order_id', null)
                ->first();
                
            if ($transaction) {
                $transaction->update([
                    'order_id' => $order->id,
                    'status' => 'completed'
                ]);
                
                Log::info('bKash transaction linked to order', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in bKash OrderPlacement listener: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
