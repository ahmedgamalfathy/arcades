<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Daily\Daily;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Enums\BookedDevice\BookedDeviceEnum;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

echo "=== Testing Simple Timer Creation with Activity Log ===\n\n";

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
        echo "✅ Created Daily ID: {$daily->id}\n";
    } else {
        echo "📋 Using Daily ID: {$daily->id}\n";
    }

    // Create SessionDevice without events
    $sessionDevice = SessionDevice::withoutEvents(function () use ($daily) {
        return SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id
        ]);
    });

    echo "✅ Created SessionDevice ID: {$sessionDevice->id}\n";

    // Find an available device ID from existing devices
    $existingDeviceIds = DB::table('devices')->pluck('id')->toArray();
    $usedDeviceIds = BookedDevice::whereIn('status', [
        BookedDeviceEnum::ACTIVE->value,
        BookedDeviceEnum::PAUSED->value
    ])->pluck('device_id')->toArray();

    $availableDeviceId = null;
    foreach ($existingDeviceIds as $deviceId) {
        if (!in_array($deviceId, $usedDeviceIds)) {
            $availableDeviceId = $deviceId;
            break;
        }
    }

    if (!$availableDeviceId) {
        echo "❌ No available devices found. Using device ID 1 anyway for testing...\n";
        $availableDeviceId = 1;

        // Finish any active bookings for device 1 to make it available
        BookedDevice::where('device_id', 1)
            ->whereIn('status', [BookedDeviceEnum::ACTIVE->value, BookedDeviceEnum::PAUSED->value])
            ->update(['status' => BookedDeviceEnum::FINISHED->value]);

        echo "✅ Finished existing bookings for device 1\n";
    }

    echo "🎯 Using available device ID: {$availableDeviceId}\n";

    // Create BookedDevice data
    $deviceData = [
        'sessionDeviceId' => $sessionDevice->id,
        'deviceTypeId' => 1,
        'deviceId' => $availableDeviceId,
        'deviceTimeId' => 1,
        'startDateTime' => now(),
        'endDateTime' => null,
        'totalUsedSeconds' => 0,
        'status' => BookedDeviceEnum::ACTIVE->value,
    ];

    // Create service and device
    $service = new BookedDeviceService();
    $device = $service->createBookedDeviceWithoutLog($deviceData);

    echo "✅ Created BookedDevice ID: {$device->id}\n";
    echo "Device ID: {$device->device_id}\n";
    echo "Session ID: {$device->session_device_id}\n\n";

    // Create manual activity log with proper keys
    $dailyId = $daily->id;
    $deviceStartDate = now()->format('Y-m-d');
    $deviceSessionKey = 'device_' . $device->device_id . '_daily_' . $dailyId . '_' . $deviceStartDate;
    $timerId = 'timer_' . $device->device_id . '_' . $device->created_at->timestamp;

    echo "🔑 Generated Keys:\n";
    echo "Device Session Key: {$deviceSessionKey}\n";
    echo "Timer ID: {$timerId}\n\n";

    activity()
        ->useLog('SessionDevice')
        ->event('created')
        ->performedOn($sessionDevice)
        ->withProperties([
            'attributes' => [
                'id' => $sessionDevice->id,
                'name' => $sessionDevice->name,
                'type' => $sessionDevice->type,
            ],
            'old' => [
                'name' => '',
                'type' => '',
            ],
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

    echo "✅ Created activity log\n\n";

    // Test activity log retrieval
    echo "🔍 Testing activity log retrieval...\n";
    $activities = $service->getActivityLogToDevice($device->id);

    echo "Activities count: " . $activities->count() . "\n\n";

    if ($activities->count() > 0) {
        echo "📝 Retrieved activities:\n";
        foreach ($activities as $activity) {
            echo "  - Event: {$activity->event}\n";
            echo "    Log Name: {$activity->log_name}\n";
            echo "    Description: {$activity->description}\n";
            echo "    Created: {$activity->created_at}\n";

            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            if (isset($properties['timer_id'])) {
                echo "    ✅ Timer ID: {$properties['timer_id']}\n";
            }
            if (isset($properties['device_session_key'])) {
                echo "    ✅ Device Session Key: {$properties['device_session_key']}\n";
            }
            if (isset($properties['device_id'])) {
                echo "    Device ID: {$properties['device_id']}\n";
            }
            if (isset($properties['session_type'])) {
                echo "    Session Type: {$properties['session_type']}\n";
            }
            echo "    ---\n";
        }

        echo "\n🎯 Verification:\n";
        $firstActivity = $activities->first();
        $firstProperties = is_string($firstActivity->properties)
            ? json_decode($firstActivity->properties, true)
            : ($firstActivity->properties ?? []);

        // Check timer_id consistency
        if (isset($firstProperties['timer_id']) && $firstProperties['timer_id'] === $timerId) {
            echo "✅ Timer ID is consistent and correct\n";
        } else {
            echo "❌ Timer ID mismatch\n";
        }

        // Check device_session_key
        if (isset($firstProperties['device_session_key']) && $firstProperties['device_session_key'] === $deviceSessionKey) {
            echo "✅ Device Session Key is consistent and correct\n";
        } else {
            echo "❌ Device Session Key mismatch\n";
        }

        // Check if it's today's data only
        $isToday = $firstActivity->created_at->isToday();
        echo $isToday ? "✅ Activity is from today\n" : "❌ Activity is not from today\n";

    } else {
        echo "❌ No activities retrieved\n";
    }

    DB::rollBack(); // Don't save test data
    echo "\n🔄 Rolled back test data\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Simple Timer Creation Test Complete ===\n";

?>
