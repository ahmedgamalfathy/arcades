<?php

use App\Http\Controllers\API\V1\Dashboard\Device\DeviceController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTimeController;
use App\Http\Controllers\API\V1\Dashboard\Device\DeviceTypeController;
use App\Http\Controllers\API\V1\Dashboard\Timer\BookedDeviceController;
use App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController;
use App\Http\Controllers\API\V1\Dashboard\Timer\EndGroupTimes\EndGroupTimesEditedController;
use App\Http\Controllers\API\V1\Dashboard\Timer\SessionDevice\SessionDeviceController;
use Illuminate\Support\Facades\Route;

Route::get('timers', [DeviceController::class, 'index'])->middleware('permission:devices');
Route::post('timers', [DeviceController::class, 'store'])->middleware('permission:create_devices');
Route::post('timers/{timer}/change-status', [DeviceController::class, 'changeStatus'])->middleware('permission:change_device_status');
Route::put('timers/{timer}', [DeviceController::class, 'update'])->middleware('permission:update_device');
Route::patch('timers/{timer}', [DeviceController::class, 'update'])->middleware('permission:update_device');
Route::delete('timers/{timer}', [DeviceController::class, 'destroy'])->middleware('permission:destroy_device');
Route::post('timers/{timer}/restore', [DeviceController::class, 'restore'])->middleware('permission:destroy_device');
Route::delete('timers/{timer}/force', [DeviceController::class, 'forceDelete'])->middleware('permission:destroy_device');

Route::post('devices/create-order-device', [DeviceController::class, 'createOrderDevice'])->middleware('permission:create_devices');
Route::put('devices/{id}/changeStatus', [DeviceController::class, 'changeStatus'])->middleware('permission:change_device_status');
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
