<?php

use Ihasan\Bkash\Bkash;
use Ihasan\Bkash\Services\BkashPaymentService;

it('can instantiate the bkash service', function () {
    $bkash = app(Bkash::class);
    
    expect($bkash)->toBeInstanceOf(Bkash::class);
});

it('has the required methods', function () {
    $bkash = app(Bkash::class);
    
    expect($bkash)->toHaveMethod('createPayment');
    expect($bkash)->toHaveMethod('executePayment');
    expect($bkash)->toHaveMethod('processCallback');
    expect($bkash)->toHaveMethod('getCredentials');
    expect($bkash)->toHaveMethod('getToken');
});

it('can access bkash facade', function () {
    expect(class_exists('Ihasan\Bkash\Facades\Bkash'))->toBeTrue();
});

it('has proper package configuration', function () {
    expect(config('bagisto-bkash'))->toBeArray();
    expect(config('bagisto-bkash.payment_methods'))->toBeArray();
    expect(config('bagisto-bkash.system_config'))->toBeArray();
});