<?php

namespace Ihasan\Bkash\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ihasan\Bkash\Bkash
 */
class Bkash extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ihasan\Bkash\Bkash::class;
    }
}
