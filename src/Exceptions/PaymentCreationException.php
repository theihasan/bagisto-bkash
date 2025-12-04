<?php

namespace Ihasan\Bkash\Exceptions;

class PaymentCreationException extends \Exception
{
    public function __construct($message = 'Failed to create bkash payment')
    {
        parent::__construct($message);
    }
}
