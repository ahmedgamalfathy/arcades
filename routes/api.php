<?php

use App\Http\Controllers\API\V1\Dashboard\Auth\LoginController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ChangePasswordController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\SendCodeController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\VerifyCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')->middleware('set_locale')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', LoginController::class)->middleware('throttle:login');
    });

    Route::prefix('forgot-password')->group(function () {
        Route::post('send-code', SendCodeController::class);
        Route::post('verify-code', VerifyCodeController::class);
        Route::post('change-password', ChangePasswordController::class);
    });
});

Route::prefix('v1/admin')
    ->middleware(['set_locale', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        require __DIR__.'/api/admin.php';
        require __DIR__.'/api/timers.php';
    });
