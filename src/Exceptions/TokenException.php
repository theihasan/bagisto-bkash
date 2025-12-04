<?php

namespace Ihasan\Bkash\Exceptions;

class TokenException extends \Exception
{
    public function __construct($message = 'bkash token not found')
    {
        parent::__construct($message);
    }
}