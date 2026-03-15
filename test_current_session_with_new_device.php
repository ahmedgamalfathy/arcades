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

echo "=== Testing Current Session with New Device ===\n\n";

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

    $service = new BookedDeviceService();
    $device = $service->createBookedDeviceWithoutLog([
        'sessionDeviceId' => $sessionDevice->id,
        'deviceTypeId' => 1,
        'deviceId' => 1,
        'deviceTimeId' => 1,
        'startDateTime' => now(),
        'endDateTime' => null,
        'totalUsedSeconds' => 0,
        'status' => BookedDeviceEnum::ACTIVE->value,
    ]);

    echo "✅ Created new device ID: {$device->id}\n";

    // Create activity log
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

    echo "✅ Created activity log with timer_id: {$timerId}\n\n";

    // Test activity log retrieval
    echo "🔍 Testing activity log for new device...\n";
    $activities = $service->getActivityLogToDevice($device->id);

    echo "📊 Activities count: " . $activities->count() . "\n\n";

    if ($activities->count() > 0) {
        echo "📝 Current session activities:\n";
        foreach ($activities as $activity) {
            echo "  - Event: {$activity->event}\n";
            echo "    Date: {$activity->created_at->format('Y-m-d H:i:s')}\n";
            echo "    Is Today: " . ($activity->created_at->isToday() ? 'YES' : 'NO') . "\n";

            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            if (isset($properties['timer_id'])) {
                echo "    Timer ID: {$properties['timer_id']}\n";
            }
            if (isset($properties['current_session_only'])) {
                echo "    Current Session Only: {$properties['current_session_only']}\n";
            }
            echo "    ---\n";
        }

        echo "\n✅ SUCCESS: New device shows current session activities only\n";
    } else {
        echo "❌ No activities found for new device\n";
    }

    DB::rollBack(); // Don't save test data
    echo "\n🔄 Rolled back test data\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Current Session Test Complete ===\n";

?>
