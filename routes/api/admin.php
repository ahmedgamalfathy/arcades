<?php

use App\Http\Controllers\API\V1\Dashboard\Auth\LogoutController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController;
use App\Http\Controllers\API\V1\Dashboard\Daily\DailyController;
use App\Http\Controllers\API\V1\Dashboard\Expense\ExpenseController;
use App\Http\Controllers\API\V1\Dashboard\Maintenance\MaintenanceController;
use App\Http\Controllers\API\V1\Dashboard\Media\MediaController;
use App\Http\Controllers\API\V1\Dashboard\Notification\NotificationController;
use App\Http\Controllers\API\V1\Dashboard\Order\OrderController;
use App\Http\Controllers\API\V1\Dashboard\Product\ProductController;
use App\Http\Controllers\API\V1\Dashboard\Report\DailyReportStatusController;
use App\Http\Controllers\API\V1\Dashboard\Report\ReportController;
use App\Http\Controllers\API\V1\Dashboard\Setting\Param\ParamController;
use App\Http\Controllers\API\V1\Dashboard\User\ChangeCurrentPasswordController;
use App\Http\Controllers\API\V1\Dashboard\User\UserController;
use App\Http\Controllers\API\V1\Dashboard\User\UserProfileController;
use App\Http\Controllers\API\V1\Select\SelectController;
use App\Http\Controllers\API\V2\Dashboard\Report\AllDailyRangeOfDateController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('logout', LogoutController::class);
});

Route::put('orders/{id}/changeStatus', [OrderController::class, 'changeOrderStatus']);
Route::put('orders/{id}/changeTypePay', [OrderController::class, 'changeOrderPaymentStatus']);
Route::post('orders/{id}/restore', [OrderController::class, 'restore'])
    ->middleware('permission:restore-orders');
Route::delete('orders/{id}/force', [OrderController::class, 'forceDelete'])
    ->middleware('permission:force-delete-orders');
Route::delete('orders/{id}/force-delete', [OrderController::class, 'forceDelete'])
    ->middleware('permission:force-delete-orders');
Route::apiResource('orders', OrderController::class);

Route::put('users/changePassword', [UserController::class, 'userChangePassword']);
Route::post('users/{id}/restore', [UserController::class, 'restore'])
    ->middleware('permission:restore-users');
Route::delete('users/{id}/force', [UserController::class, 'forceDelete'])
    ->middleware('permission:force-delete-users');
Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])
    ->middleware('permission:force-delete-users');
Route::apiResource('users', UserController::class);

Route::apiResource('media', MediaController::class);

Route::post('expenses/{id}/restore', [ExpenseController::class, 'restore'])
    ->middleware('permission:destroy_restore');
Route::delete('expenses/{id}/force', [ExpenseController::class, 'forceDelete'])
    ->middleware('permission:destroy_forceDelete');
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
    Route::delete('{id}', 'destroy');
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

Route::get('maintenances', [MaintenanceController::class, 'index'])->middleware('permission:maintenances');
Route::post('maintenances', [MaintenanceController::class, 'store'])->middleware('permission:create_maintenance');
Route::get('maintenances/{maintenance}', [MaintenanceController::class, 'show'])->middleware('permission:edit_maintenance');
Route::put('maintenances/{maintenance}', [MaintenanceController::class, 'update'])->middleware('permission:update_maintenance');
Route::patch('maintenances/{maintenance}', [MaintenanceController::class, 'update'])->middleware('permission:update_maintenance');
Route::delete('maintenances/{maintenance}', [MaintenanceController::class, 'destroy'])->middleware('permission:destroy_maintenance');

Route::prefix('reports')->middleware('permission:view_reports')->group(function () {
    Route::get('', [ReportController::class, 'getReport']);
    Route::get('getStatusReport', [ReportController::class, 'getStatusReport']);
    Route::get('getExpensesReport', [ReportController::class, 'getExpensesReport']);
    Route::get('dailyStatusData', [DailyReportStatusController::class, 'getStatusReport']);
    Route::get('all-daily-range-of-date', AllDailyRangeOfDateController::class);
});
