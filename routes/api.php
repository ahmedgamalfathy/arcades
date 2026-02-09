<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Events\BookedDeviceExpireTime;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\API\V1\Select\SelectController;
use App\Http\Controllers\API\V1\Dashboard\User\UserController;
use App\Http\Controllers\API\V1\Dashboard\Auth\LoginController;
use App\Http\Controllers\API\V1\Dashboard\Auth\LogoutController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyController;
use App\Http\Controllers\API\V1\Dashboard\Media\MediaController;
use App\Http\Controllers\API\V1\Dashboard\Order\OrderController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceController;
use App\Http\Controllers\API\V1\Dashboard\Report\ReportController;
use App\Http\Controllers\API\V1\Dashboard\Expense\ExpenseController;
use App\Http\Controllers\API\V1\Dashboard\Product\ProductController;
use App\Http\Controllers\API\V1\Dashboard\User\UserProfileController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyReportController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTimeController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTypeController;
use App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController;
use App\Http\Controllers\API\V1\Dashboard\Timer\BookedDeviceController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController;
use App\Http\Controllers\API\V1\Dashboard\Setting\Param\ParamController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\SendCodeController;
use App\Http\Controllers\API\V1\Dashboard\Maintenance\MaintenanceController;
use App\Http\Controllers\API\V1\Dashboard\Report\DailyReportStatusController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ResendCodeController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\VerifyCodeController;
use App\Http\Controllers\API\V1\Dashboard\Notification\NotificationController;
use App\Http\Controllers\API\V1\Dashboard\User\ChangeCurrentPasswordController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ChangePasswordController;
use App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes\EndGroupTimesController;
use App\Http\Controllers\API\V1\Dashboard\Timer\SessionDevice\SessionDeviceController;
use App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes\EndGroupTimesEditedController;
use App\Http\Controllers\API\V2\Dashboard\Report\AllDailyRangeOfDateController;


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
                    Route::put('orders/{id}/changeStatus',[OrderController::class,'changeOrderStatus']);
                    Route::put('orders/{id}/changeTypePay',[OrderController::class,'changeOrderPaymentStatus']);
                    Route::put('users/changePassword',[UserController::class,'userChangePassword']);
                    Route::post('users/{id}/restore', [UserController::class, 'restore']);
                    Route::delete('users/{id}/force', [UserController::class, 'forceDelete']);
                    Route::post('expenses/{id}/restore', [ExpenseController::class, 'restore']);
                    Route::delete('expenses/{id}/force', [ExpenseController::class, 'forceDelete']);
                    Route::apiResource('users', UserController::class);
                    Route::apiResource('media', MediaController::class);
                    Route::apiResource('products', ProductController::class);
                    Route::post('products/{id}/restore', [ProductController::class, 'restore']);
                    Route::delete('products/{id}/force', [ProductController::class, 'forceDelete']);
                    Route::apiResource('orders', OrderController::class);
                    Route::post('orders/{id}/restore', [OrderController::class, 'restore']);
                    Route::delete('orders/{id}/force', [OrderController::class, 'forceDelete']);
                    Route::apiResource('expenses', ExpenseController::class);
                    Route::put('profile/change-password', ChangeCurrentPasswordController::class);
                    Route::apiSingleton('profile', UserProfileController::class);
                    Route::post('devices/create-order-device', [DeviceController::class, 'createOrderDevice']);
                    Route::put('devices/{id}/changeStatus',[DeviceController::class,'changeStatus']);
                    Route::post('devices/{id}/restore', [DeviceController::class, 'restore']);
                    Route::delete('devices/{id}/force', [DeviceController::class, 'forceDelete']);
                    Route::post('device-types/{id}/restore', [DeviceTypeController::class, 'restore']);
                    Route::delete('device-types/{id}/force', [DeviceTypeController::class, 'forceDelete']);
                    Route::post('device-times/{id}/restore', [DeviceTimeController::class, 'restore']);
                    Route::delete('device-times/{id}/force', [DeviceTimeController::class, 'forceDelete']);
                    Route::apiResource('device-types', DeviceTypeController::class);
                    Route::apiResource('device-times', DeviceTimeController::class);
                    Route::get('get-device-times',[DeviceController::class,'getTimesByDeviceId']);
                    Route::apiResource('devices', DeviceController::class);
                    Route::apiResource('maintenances', MaintenanceController::class);
                    Route::post('/device-timer/end-group-times',EndGroupTimesEditedController::class);
                    Route::get('device-timer/booked-devices', [BookedDeviceController::class, 'allBookedDevices']);
                    Route::post('device-timer/{id}/restore', [DeviceTimerController::class, 'restore']);
                    Route::delete('device-timer/{id}/force', [DeviceTimerController::class, 'forceDelete']);

                    Route::prefix('device-timer')->controller(DeviceTimerController::class)->group(function () {
                        Route::post('individual-time', 'individualTime');
                        Route::post('group-time', 'groupTime');
                        Route::post('{id}/finish', 'finish');
                        Route::post('{id}/pause', 'pause');
                        Route::post('{id}/resume', 'resume');
                        Route::get('{id}/activity-log','getActitvityLogToDevice');
                        Route::post('{id}/change-time', 'changeTime');
                        Route::get('{id}/show','show');
                        Route::delete('{id}/delete','destroy');
//                        Route::post('{id}/restore', 'restore');
//                        Route::delete('{id}/force', 'forceDelete');
                        Route::put('{id}/update-end-date-time', 'updateEndDateTime');
                        Route::put('{id}/transfer_device_to_group','transferDeviceToGroup');
                        Route::put('{id}/transfer_booked_device_to_session_device','transferBookedDeviceToSessionDevice');
                    });
                    Route::post('sessions/{id}/restore', [SessionDeviceController::class, 'restore']);
                    Route::delete('sessions/{id}/force', [SessionDeviceController::class, 'forceDelete']);
                    Route::apiResource('sessions', SessionDeviceController::class)->only(['index','show','destroy','update']);
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
                        Route::get('daily-report', 'dailyReport');
                        Route::get('monthly-chart-data', 'monthlyChartData');
                        Route::get('activity-log', 'activityLog');
                        Route::get('check-booked-device-open', 'checkBookedDeviceOpen');
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
                    Route::get('reports/dailyStatusData',[ DailyReportStatusController::class,'getStatusReport']);
                    Route::get('dailyActivity',DailyActivityController::class);
                    Route::get('reports/all-daily-range-of-date', AllDailyRangeOfDateController::class);
                    });

//                    Route::prefix('v2/admin')->group(function () {
//                        Route::get('reports/all-daily-range-of-date', AllDailyRangeOfDateController::class);
//                    });
