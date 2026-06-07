<?php

use Illuminate\Support\Facades\Route;
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
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTimeController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTypeController;
use App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController;
use App\Http\Controllers\API\V1\Dashboard\Timer\BookedDeviceController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController;
use App\Http\Controllers\API\V1\Dashboard\Setting\Param\ParamController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\SendCodeController;
use App\Http\Controllers\API\V1\Dashboard\Maintenance\MaintenanceController;
use App\Http\Controllers\API\V1\Dashboard\Report\DailyReportStatusController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\VerifyCodeController;
use App\Http\Controllers\API\V1\Dashboard\Notification\NotificationController;
use App\Http\Controllers\API\V1\Dashboard\User\ChangeCurrentPasswordController;
use App\Http\Controllers\API\V1\Dashboard\ForgotPassword\ChangePasswordController;
use App\Http\Controllers\API\V1\Dashboard\Timer\SessionDevice\SessionDeviceController;
use App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes\EndGroupTimesEditedController;
use App\Http\Controllers\API\V2\Dashboard\Report\AllDailyRangeOfDateController;

Route::prefix('v1/admin')->middleware('set_locale')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', LoginController::class);
    });

    Route::prefix('forgot-password')->group(function () {
        Route::post('send-code', SendCodeController::class);
        Route::post('verify-code', VerifyCodeController::class);
        Route::post('change-password', ChangePasswordController::class);
    });
});

Route::prefix('v1/admin')
    ->middleware(['set_locale', 'auth:sanctum', 'tenant'])
    ->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', LogoutController::class);
        });

        Route::put('orders/{id}/changeStatus', [OrderController::class, 'changeOrderStatus']);
        Route::put('orders/{id}/changeTypePay', [OrderController::class, 'changeOrderPaymentStatus']);
        Route::post('orders/{id}/restore', [OrderController::class, 'restore']);
        Route::delete('orders/{id}/force', [OrderController::class, 'forceDelete']);
        Route::apiResource('orders', OrderController::class);

        Route::put('users/changePassword', [UserController::class, 'userChangePassword']);
        Route::post('users/{id}/restore', [UserController::class, 'restore']);
        Route::delete('users/{id}/force', [UserController::class, 'forceDelete']);
        Route::apiResource('users', UserController::class);

        Route::apiResource('media', MediaController::class);

        Route::post('expenses/{id}/restore', [ExpenseController::class, 'restore']);
        Route::delete('expenses/{id}/force', [ExpenseController::class, 'forceDelete']);
        Route::apiResource('expenses', ExpenseController::class);

        Route::put('profile/change-password', ChangeCurrentPasswordController::class);
        Route::apiSingleton('profile', UserProfileController::class);

        Route::prefix('parameter')->group(function () {
            Route::apiResource('params', ParamController::class);
        });

        Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
            Route::get('/', 'notifications');
            Route::get('/auth_unread_notifications', 'auth_unread_notifications');
            Route::get('/auth_read_notifications', 'auth_read_notifications');
            Route::get('/auth_read_notifications/{id}', 'auth_read_notification');
            Route::delete('/auth_delete_notifications', 'auth_delete_notifications');
            Route::delete('/auth_delete_notifications/{id}', 'auth_delete_notification');
        });

        Route::prefix('selects')->group(function () {
            Route::get('', [SelectController::class, 'getSelects']);
        });

        Route::prefix('dailies')->controller(DailyController::class)->group(function () {
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

        Route::get('dailyActivity', DailyActivityController::class);

        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:create_products');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:edit_product');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:update_product');
        Route::patch('products/{product}', [ProductController::class, 'update'])->middleware('permission:update_product');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:destroy_product');
        Route::post('products/{id}/restore', [ProductController::class, 'restore'])->middleware('permission:destroy_product');
        Route::delete('products/{id}/force', [ProductController::class, 'forceDelete'])->middleware('permission:destroy_product');

        Route::get('timers', [DeviceController::class, 'index'])->middleware('permission:devices');
        Route::post('timers', [DeviceController::class, 'store'])->middleware('permission:create_devices');
        Route::post('timers/{timer}/change-status', [DeviceController::class, 'changeStatus'])->middleware('permission:devices_changeStatus');
        Route::put('timers/{timer}', [DeviceController::class, 'update'])->middleware('permission:edit_device');
        Route::patch('timers/{timer}', [DeviceController::class, 'update'])->middleware('permission:edit_device');
        Route::delete('timers/{timer}', [DeviceController::class, 'destroy'])->middleware('permission:destroy_device');
        Route::post('timers/{timer}/restore', [DeviceController::class, 'restore'])->middleware('permission:destroy_device');
        Route::delete('timers/{timer}/force', [DeviceController::class, 'forceDelete'])->middleware('permission:destroy_device');

        Route::post('devices/create-order-device', [DeviceController::class, 'createOrderDevice'])->middleware('permission:create_devices');
        Route::put('devices/{id}/changeStatus', [DeviceController::class, 'changeStatus'])->middleware('permission:devices_changeStatus');
        Route::post('devices/{id}/restore', [DeviceController::class, 'restore'])->middleware('permission:destroy_device');
        Route::delete('devices/{id}/force', [DeviceController::class, 'forceDelete'])->middleware('permission:destroy_device');
        Route::get('devices', [DeviceController::class, 'index'])->middleware('permission:devices');
        Route::post('devices', [DeviceController::class, 'store'])->middleware('permission:create_devices');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->middleware('permission:edit_device');
        Route::put('devices/{device}', [DeviceController::class, 'update'])->middleware('permission:update_device');
        Route::patch('devices/{device}', [DeviceController::class, 'update'])->middleware('permission:update_device');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->middleware('permission:destroy_device');

        Route::post('device-types/{id}/restore', [DeviceTypeController::class, 'restore'])->middleware('permission:destroy_deviceType');
        Route::delete('device-types/{id}/force', [DeviceTypeController::class, 'forceDelete'])->middleware('permission:destroy_deviceType');
        Route::get('device-types', [DeviceTypeController::class, 'index'])->middleware('permission:deviceTypes');
        Route::post('device-types', [DeviceTypeController::class, 'store'])->middleware('permission:create_deviceTypes');
        Route::get('device-types/{device_type}', [DeviceTypeController::class, 'show'])->middleware('permission:edit_deviceType');
        Route::put('device-types/{device_type}', [DeviceTypeController::class, 'update'])->middleware('permission:update_deviceType');
        Route::patch('device-types/{device_type}', [DeviceTypeController::class, 'update'])->middleware('permission:update_deviceType');
        Route::delete('device-types/{device_type}', [DeviceTypeController::class, 'destroy'])->middleware('permission:destroy_deviceType');

        Route::post('device-times/{id}/restore', [DeviceTimeController::class, 'restore'])->middleware('permission:destroy_deviceTime');
        Route::delete('device-times/{id}/force', [DeviceTimeController::class, 'forceDelete'])->middleware('permission:destroy_deviceTime');
        Route::get('device-times', [DeviceTimeController::class, 'index'])->middleware('permission:deviceTimes');
        Route::post('device-times', [DeviceTimeController::class, 'store'])->middleware('permission:create_deviceTimes');
        Route::get('device-times/{device_time}', [DeviceTimeController::class, 'show'])->middleware('permission:edit_deviceTime');
        Route::put('device-times/{device_time}', [DeviceTimeController::class, 'update'])->middleware('permission:update_deviceTime');
        Route::patch('device-times/{device_time}', [DeviceTimeController::class, 'update'])->middleware('permission:update_deviceTime');
        Route::delete('device-times/{device_time}', [DeviceTimeController::class, 'destroy'])->middleware('permission:destroy_deviceTime');

        Route::get('get-device-times', [DeviceController::class, 'getTimesByDeviceId'])->middleware('permission:devices');
        Route::apiResource('maintenances', MaintenanceController::class);

        Route::post('/device-timer/end-group-times', EndGroupTimesEditedController::class)->middleware('permission:update_device');
        Route::get('device-timer/booked-devices', [BookedDeviceController::class, 'allBookedDevices'])->middleware('permission:devices');
        Route::post('device-timer/{id}/restore', [DeviceTimerController::class, 'restore'])->middleware('permission:destroy_device');
        Route::delete('device-timer/{id}/force', [DeviceTimerController::class, 'forceDelete'])->middleware('permission:destroy_device');

        Route::prefix('device-timer')->controller(DeviceTimerController::class)->group(function () {
            Route::post('individual-time', 'individualTime')->middleware('permission:create_devices');
            Route::post('group-time', 'groupTime')->middleware('permission:create_devices');
            Route::post('{id}/finish', 'finish')->middleware('permission:update_device');
            Route::post('{id}/pause', 'pause')->middleware('permission:update_device');
            Route::post('{id}/resume', 'resume')->middleware('permission:update_device');
            Route::get('{id}/activity-log', 'getActitvityLogToDevice')->middleware('permission:devices');
            Route::post('{id}/change-time', 'changeTime')->middleware('permission:update_device');
            Route::get('{id}/show', 'show')->middleware('permission:edit_device');
            Route::delete('{id}/delete', 'destroy')->middleware('permission:destroy_device');
            Route::put('{id}/update-end-date-time', 'updateEndDateTime')->middleware('permission:update_device');
            Route::put('{id}/transfer_device_to_group', 'transferDeviceToGroup')->middleware('permission:update_device');
            Route::put('{id}/transfer_booked_device_to_session_device', 'transferBookedDeviceToSessionDevice')->middleware('permission:update_device');
        });

        Route::post('sessions/{id}/restore', [SessionDeviceController::class, 'restore'])->middleware('permission:destroy_device');
        Route::delete('sessions/{id}/force', [SessionDeviceController::class, 'forceDelete'])->middleware('permission:destroy_device');
        Route::get('sessions', [SessionDeviceController::class, 'index'])->middleware('permission:devices');
        Route::get('sessions/{session}', [SessionDeviceController::class, 'show'])->middleware('permission:edit_device');
        Route::put('sessions/{session}', [SessionDeviceController::class, 'update'])->middleware('permission:update_device');
        Route::patch('sessions/{session}', [SessionDeviceController::class, 'update'])->middleware('permission:update_device');
        Route::delete('sessions/{session}', [SessionDeviceController::class, 'destroy'])->middleware('permission:destroy_device');

        Route::prefix('reports')->middleware('permission:view_reports')->group(function () {
            Route::get('', [ReportController::class, 'getReport']);
            Route::get('getStatusReport', [ReportController::class, 'getStatusReport']);
            Route::get('getExpensesReport', [ReportController::class, 'getExpensesReport']);
            Route::get('dailyStatusData', [DailyReportStatusController::class, 'getStatusReport']);
            Route::get('all-daily-range-of-date', AllDailyRangeOfDateController::class);
        });
    });
