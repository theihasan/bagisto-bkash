<?php

use Illuminate\Support\Facades\Route;
use Webkul\BkashPayment\Http\Controllers\BkashPaymentController;

Route::group(['middleware' => ['web']], function () {
    Route::prefix('bkash')->group(function () {
        Route::get('callback', [BkashPaymentController::class, 'callback'])->name('bkash.payment.callback');
        Route::get('success', [BkashPaymentController::class, 'success'])->name('bkash.payment.success');
        Route::get('fail', [BkashPaymentController::class, 'fail'])->name('bkash.payment.fail');
        Route::get('cancel', [BkashPaymentController::class, 'cancel'])->name('bkash.payment.cancel');
    });
});
