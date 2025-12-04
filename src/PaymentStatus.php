<?php

namespace Ihasan\Bkash;

enum PaymentStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    const COMPLETED = 'completed';
}
