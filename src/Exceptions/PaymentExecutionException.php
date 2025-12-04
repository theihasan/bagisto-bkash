<?php

namespace Ihasan\Bkash\Exceptions;

class PaymentExecutionException extends \Exception
{
    public function __construct($message = 'Failed to execute payment')
    {
        parent::__construct($message);
    }
}
