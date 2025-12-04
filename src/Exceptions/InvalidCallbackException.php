<?php

namespace Ihasan\Bkash\Exceptions;

class InvalidCallbackException extends \Exception
{
    public function __construct($message = 'Invalid callback data')
    {
        parent::__construct($message);
    }
}