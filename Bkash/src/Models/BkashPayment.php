<?php

namespace Webkul\Bkash\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Checkout\Models\Cart;
use Webkul\Sales\Models\Order;

class BkashPayment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bkash_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_id',
        'token',
        'amount',
        'invoice_number',
        'status',
        'transaction_id',
        'cart_id',
        'order_id',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Get the cart that the payment belongs to.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the order that the payment belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get transaction ID from meta if available.
     *
     * @return string|null
     */
    public function getTransactionIdAttribute()
    {
        if ($this->attributes['transaction_id']) {
            return $this->attributes['transaction_id'];
        }

        if (is_array($this->meta) && isset($this->meta['trxID'])) {
            return $this->meta['trxID'];
        }

        return null;
    }

    /**
     * Get payment status description.
     */
    public function getStatusDescriptionAttribute(): string
    {
        $statusMap = [
            'pending'   => 'Payment is pending',
            'success'   => 'Payment was successful',
            'completed' => 'Payment was completed',
            'failed'    => 'Payment failed',
            'cancelled' => 'Payment was cancelled',
            'refunded'  => 'Payment was refunded',
        ];

        return $statusMap[$this->status] ?? 'Unknown status';
    }
}
