<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Services\Timer\DeviceTimerService;
use App\Services\Timer\BookedDevicePauseService;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Daily\Daily;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Enums\BookedDevice\BookedDeviceEnum;
use Illuminate\Support\Facades\DB;

echo "=== Testing Pause/Resume Activity Log ===\n\n";

try {
    DB::beginTransaction();

    // Get or create a daily record
    $daily = Daily::orderBy('created_at', 'desc')->first();
    if (!$daily) {
        $daily = Daily::create([
            'name' => 'Test Daily ' . date('Y-m-d H:i:s'),
            'date' => now()->format('Y-m-d'),
            'status' => 1
        ]);
    }

    echo "📋 Using Daily ID: {$daily->id}\n";

    // Clean up any existing bookings for device 1
    BookedDevice::where('device_id', 1)
        ->whereIn('status', [BookedDeviceEnum::ACTIVE->value, BookedDeviceEnum::PAUSED->value])
        ->update(['status' => BookedDeviceEnum::FINISHED->value]);

    // Create new session and device
    $sessionDevice = SessionDevice::withoutEvents(function () use ($daily) {
        return SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id
        ]);
    });

    $bookedDeviceService = new BookedDeviceService();
    $device = $bookedDeviceService->createBookedDeviceWithoutLog([
        'sessionDeviceId' => $sessionDevice->id,
        'deviceTypeId' => 1,
        'deviceId' => 1,
        'deviceTimeId' => 1,
        'startDateTime' => now(),
        'endDateTime' => null,
        'totalUsedSeconds' => 0,
        'status' => BookedDeviceEnum::ACTIVE->value,
    ]);

    echo "✅ Created device ID: {$device->id}\n";

    // Create initial activity log
    $timerId = 'timer_' . $device->device_id . '_' . $device->created_at->timestamp;
    $deviceSessionKey = 'device_' . $device->device_id . '_daily_' . $daily->id . '_' . now()->format('Y-m-d');

    activity()
        ->useLog('SessionDevice')
        ->event('created')
        ->performedOn($sessionDevice)
        ->withProperties([
            'attributes' => ['id' => $sessionDevice->id, 'name' => $sessionDevice->name, 'type' => $sessionDevice->type],
            'old' => ['name' => '', 'type' => ''],
            'children' => [[
                'id' => $device->id,
                'event' => 'created',
                'log_name' => 'BookedDevice',
                'device_id' => $device->device_id,
                'device_type_id' => $device->device_type_id,
                'device_time_id' => $device->device_time_id,
                'status' => $device->status,
            ]],
            'device_session_key' => $deviceSessionKey,
            'timer_id' => $timerId,
            'session_type' => 'individual'
        ])
        ->tap(function ($activity) use ($sessionDevice) {
            $activity->daily_id = $sessionDevice->daily_id;
        })
        ->log('SessionDevice - Individual time created');

    echo "✅ Created initial activity log\n";

    // Create timer service
    $pauseService = new BookedDevicePauseService();
    $timerService = new DeviceTimerService($bookedDeviceService, $pauseService);

    // Test pause
    echo "\n🔄 Testing pause operation...\n";
    $timerService->pause($device->id);
    echo "✅ Device paused\n";

    // Test resume
    echo "\n🔄 Testing resume operation...\n";
    $timerService->resume($device->id);
    echo "✅ Device resumed\n";

    // Test pause again
    echo "\n🔄 Testing second pause operation...\n";
    $timerService->pause($device->id);
    echo "✅ Device paused again\n";

    // Test resume again
    echo "\n🔄 Testing second resume operation...\n";
    $timerService->resume($device->id);
    echo "✅ Device resumed again\n";

    // Check activity log
    echo "\n🔍 Checking activity log after pause/resume operations...\n";
    $activities = $bookedDeviceService->getActivityLogToDevice($device->id);

    echo "📊 Activities count: " . $activities->count() . "\n\n";

    if ($activities->count() > 0) {
        echo "📝 Activities with pause/resume operations:\n";
        foreach ($activities as $activity) {
            echo "  - Event: {$activity->event}\n";
            echo "    Description: {$activity->description}\n";
            echo "    Date: {$activity->created_at->format('Y-m-d H:i:s')}\n";

            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            if (isset($properties['timer_id'])) {
                echo "    Timer ID: {$properties['timer_id']}\n";
            }
            if (isset($properties['action_type'])) {
                echo "    Action Type: {$properties['action_type']}\n";
            }
            if (isset($properties['children']) && is_array($properties['children'])) {
                foreach ($properties['children'] as $child) {
                    if (isset($child['status']) && isset($child['old_status'])) {
                        echo "    Status Change: {$child['old_status']} → {$child['status']}\n";
                    }
                }
            }
            echo "    ---\n";
        }

        // Count pause/resume operations
        $pauseCount = 0;
        $resumeCount = 0;
        foreach ($activities as $activity) {
            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            if (isset($properties['action_type'])) {
                if ($properties['action_type'] === 'pause') {
                    $pauseCount++;
                } elseif ($properties['action_type'] === 'resume') {
                    $resumeCount++;
                }
            }
        }

        echo "\n🎯 Operation Summary:\n";
        echo "  - Pause operations: {$pauseCount}\n";
        echo "  - Resume operations: {$resumeCount}\n";

        if ($pauseCount >= 2 && $resumeCount >= 2) {
            echo "✅ SUCCESS: All pause/resume operations are logged and visible\n";
        } else {
            echo "❌ PROBLEM: Some pause/resume operations are missing\n";
        }

    } else {
        echo "❌ No activities found\n";
    }

    DB::rollBack(); // Don't save test data
    echo "\n🔄 Rolled back test data\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Pause/Resume Activity Log Test Complete ===\n";

?>
