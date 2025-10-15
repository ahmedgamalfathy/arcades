<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Dashboard\User\UserController;
use App\Http\Controllers\API\V1\Dashboard\Auth\LoginController;
use App\Http\Controllers\API\V1\Dashboard\Auth\LogoutController;
use App\Http\Controllers\API\V1\Dashboard\Media\MediaController;
use App\Http\Controllers\API\V1\Dashboard\Order\OrderController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceController;
use App\Http\Controllers\API\V1\Dashboard\Expense\ExpenseController;
use App\Http\Controllers\API\V1\Dashboard\Product\ProductController;
use App\Http\Controllers\API\V1\Dashboard\User\UserProfileController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTimeController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTypeController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\SendCodeController;
use App\Http\Controllers\API\V1\Dashboard\Maintenance\MaintenanceController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ResendCodeController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\VerifyCodeController;
use App\Http\Controllers\API\V1\Dashboard\User\ChangeCurrentPasswordController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ChangePasswordController;
use App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController;

Route::prefix('v1/admin')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('/login ', LoginController::class);
            Route::post('/logout',LogoutController::class);
        });
        Route::prefix('forgot-password')->group(function () {
            Route::post('/send-code', SendCodeController::class);
            Route::post('/verify-code', VerifyCodeController::class);
            // Route::post('/resendCode', ResendCodeController::class);
            Route::post('/change-password', ChangePasswordController::class);
        });
});

Route::prefix('v1/admin')->group(function () {
        Route::get('expenses/internal',[ExpenseController::class,'internalExpenses']);
        Route::get('expenses/external',[ExpenseController::class,'externalExpenses']);
        Route::put('users/changePassword',[UserController::class,'userChangePassword']);
        Route::post('expenses/{id}/restore', [ExpenseController::class, 'restore']);
        Route::delete('expenses/{id}/force', [ExpenseController::class, 'forceDelete']);
        Route::get('orders/internal',[OrderController::class,'internalOrders']);
        Route::get('orders/external',[OrderController::class,'externalOrders']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('media', MediaController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('orders', OrderController::class);
        Route::apiResource('expenses', ExpenseController::class)->except('index');
        Route::put('profile/change-password', ChangeCurrentPasswordController::class);
        Route::apiSingleton('profile', UserProfileController::class);
         Route::put('devices/{id}/changeStatus',[DeviceController::class,'changeStatus']);
        Route::apiResource('device-types', DeviceTypeController::class);
        Route::apiResource('device-times', DeviceTimeController::class);
        Route::apiResource('devices', DeviceController::class);
        Route::apiResource('maintenances', MaintenanceController::class);
        Route::prefix('device-timer')->controller(DeviceTimerController::class)->group(function () {
            Route::post('start-individual', 'startIndividual');
            Route::post('{id}/finish', 'finish');
            Route::post('{id}/pause', 'pause');
            Route::post('{id}/resume', 'resume');
            Route::post('{id}/change-time', 'changeTime');
        });
   });
