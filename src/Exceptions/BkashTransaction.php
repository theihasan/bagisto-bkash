<?php

namespace Webkul\BkashPayment\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Sales\Models\Order;

class BkashTransaction extends Model
{
    protected $table = 'bkash_transactions';

    protected $fillable = [
        'payment_id',
        'transaction_id',
        'cart_id',
        'order_id',
        'amount',
        'invoice_id',
        'status',
        'refund_amount',
        'response_data',
        'refund_data',
    ];

    /**
     * Get the order associated with the transaction.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
