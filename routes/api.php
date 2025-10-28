<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
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
use App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\SendCodeController;
use App\Http\Controllers\API\V1\Dashboard\Maintenance\MaintenanceController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ResendCodeController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\VerifyCodeController;
use App\Http\Controllers\API\V1\Dashboard\User\ChangeCurrentPasswordController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ChangePasswordController;
use App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes\EndGroupTimesController;
use App\Http\Controllers\API\V1\Dashboard\Timer\SessionDevice\SessionDeviceController;
use App\Http\Controllers\API\V1\Dashboard\Setting\Param\ParamController;
use App\Http\Controllers\API\V1\Dashboard\Notification\NotificationController;
use App\Http\Controllers\API\V1\Select\SelectController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyReportController;
use App\Http\Controllers\API\V1\Dashboard\Report\ReportController;
use App\Events\BookedDeviceExpireTime;

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
                Route::put('users/changePassword',[UserController::class,'userChangePassword']);
                Route::post('expenses/{id}/restore', [ExpenseController::class, 'restore']);
                Route::delete('expenses/{id}/force', [ExpenseController::class, 'forceDelete']);
                Route::apiResource('users', UserController::class);
                Route::apiResource('media', MediaController::class);
                Route::apiResource('products', ProductController::class);
                Route::apiResource('orders', OrderController::class);
                Route::apiResource('expenses', ExpenseController::class);
                Route::put('profile/change-password', ChangeCurrentPasswordController::class);
                Route::apiSingleton('profile', UserProfileController::class);
                Route::post('devices/create-order-device', [DeviceController::class, 'createOrderDevice']);
                Route::put('devices/{id}/changeStatus',[DeviceController::class,'changeStatus']);
                Route::apiResource('device-types', DeviceTypeController::class);
                Route::apiResource('device-times', DeviceTimeController::class);
                Route::apiResource('devices', DeviceController::class);
                Route::apiResource('maintenances', MaintenanceController::class);
                Route::post('/device-timer/end-group-times',EndGroupTimesController::class);
                Route::prefix('device-timer')->controller(DeviceTimerController::class)->group(function () {
                    Route::post('individual-time', 'individualTime');
                    Route::post('group-time', 'groupTime');
                    Route::post('{id}/finish', 'finish');
                    Route::post('{id}/pause', 'pause');
                    Route::post('{id}/resume', 'resume');
                    Route::post('{id}/change-time', 'changeTime');
                    Route::get('{id}/show','show');
                    Route::delete('{id}/delete','destroy');
                    Route::put('{id}/update-end-date-time', 'updateEndDateTime');
                    Route::put('{id}/transfer_device _to_group','transferDeviceToGroup');
                });
                Route::apiResource('sessions', SessionDeviceController::class)->only(['index','show','destroy']);
                Route::prefix('parameter')->group(function () {
                    Route::apiResource('params', ParamController::class);
                });
                Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
                    Route::get('/', 'notifications');
                    Route::get('/auth_unread_notifications', 'auth_unread_notifications');
                    Route::get('/auth_read_notifications', 'auth_read_notifications');
                    Route::get('/auth_read_notifications/{id}', 'auth_read_notification');
                    Route::DELETE('/auth_delete_notifications', 'auth_delete_notifications');
                    Route::DELETE('/auth_delete_notifications/{id}', 'auth_delete_notification');
                });
                Route::prefix('selects')->group(function(){
                    Route::get('', [SelectController::class, 'getSelects']);
                });
                Route::prefix('dailies')->controller(DailyController::class)->group(function(){
                    Route::get('dailyReport', 'dailyReport');
                    Route::get('', 'index');
                    Route::post('', 'create');
                    Route::get('{id}', 'show');
                    Route::put('{id}', 'update');
                    Route::delete('{id}', 'delete');
                    Route::post('close', 'closeDaily');
            
                });
                Route::prefix('reports')->controller(ReportController::class)->group(function(){
                    Route::get('', 'getReport');
                    Route::get('getStatusReport', 'getStatusReport');
                    Route::get('getExpensesReport', 'getExpensesReport');
                });
                //  Route::post('/test-notification', function () {
                //     try {
                //         // بث الحدث لكل المستخدمين
                //         broadcast(new BookedDeviceExpireTime([
                //             'sessionDevice' => 1,
                //             'deviceTypeName' => 'PlayStation 5',
                //             'deviceTimeName' => 'ساعة واحدة',
                //             'deviceName' => 'PS5-001',
                //             'bookedDeviceId' => 123,
                //             'message' => '🎮 اختبار: متبقى على الجهاز 5 دقائق',
                //         ]))->toOthers();

                //         return response()->json([
                //             'success' => true,
                //             'message' => 'Event broadcast successfully',
                //             'channel' => 'booked-device-expire-time',
                //             'event' => 'device-expire-time',
                //             'timestamp' => now()->toISOString()
                //         ]);
                        
                //     } catch (\Exception $e) {
                //         return response()->json([
                //             'success' => false,
                //             'message' => 'Error: ' . $e->getMessage(),
                //             'trace' => config('app.debug') ? $e->getTraceAsString() : null
                //         ], 500);
                //     }
                // });
    });
