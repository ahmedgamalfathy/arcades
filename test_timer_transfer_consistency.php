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

echo "=== Testing Timer Transfer Consistency ===\n\n";

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

    // Finish any existing bookings for device 1
    BookedDevice::where('device_id', 1)
        ->whereIn('status', [BookedDeviceEnum::ACTIVE->value, BookedDeviceEnum::PAUSED->value])
        ->update(['status' => BookedDeviceEnum::FINISHED->value]);

    // Step 1: Create individual session
    $individualSession = SessionDevice::withoutEvents(function () use ($daily) {
        return SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value,
            'daily_id' => $daily->id
        ]);
    });

    echo "✅ Created Individual SessionDevice ID: {$individualSession->id}\n";

    // Create BookedDevice
    $service = new BookedDeviceService();
    $device = $service->createBookedDeviceWithoutLog([
        'sessionDeviceId' => $individualSession->id,
        'deviceTypeId' => 1,
        'deviceId' => 1,
        'deviceTimeId' => 1,
        'startDateTime' => now(),
        'endDateTime' => null,
        'totalUsedSeconds' => 0,
        'status' => BookedDeviceEnum::ACTIVE->value,
    ]);

    echo "✅ Created BookedDevice ID: {$device->id}\n";

    // Create initial activity log
    $dailyId = $daily->id;
    $deviceStartDate = now()->format('Y-m-d');
    $deviceSessionKey = 'device_' . $device->device_id . '_daily_' . $dailyId . '_' . $deviceStartDate;
    $originalTimerId = 'timer_' . $device->device_id . '_' . $device->created_at->timestamp;

    echo "🔑 Original Keys:\n";
    echo "Device Session Key: {$deviceSessionKey}\n";
    echo "Original Timer ID: {$originalTimerId}\n\n";

    activity()
        ->useLog('SessionDevice')
        ->event('created')
        ->performedOn($individualSession)
        ->withProperties([
            'attributes' => [
                'id' => $individualSession->id,
                'name' => $individualSession->name,
                'type' => $individualSession->type,
            ],
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
            'timer_id' => $originalTimerId,
            'session_type' => 'individual'
        ])
        ->tap(function ($activity) use ($individualSession) {
            $activity->daily_id = $individualSession->daily_id;
        })
        ->log('SessionDevice - Individual time created');

    echo "✅ Created initial activity log\n";

    // Step 2: Create group session
    $groupSession = SessionDevice::withoutEvents(function () use ($daily) {
        return SessionDevice::create([
            'name' => 'Test Group',
            'type' => SessionDeviceEnum::GROUP->value,
            'daily_id' => $daily->id
        ]);
    });

    echo "✅ Created Group SessionDevice ID: {$groupSession->id}\n";

    // Step 3: Transfer device to group
    echo "\n🔄 Transferring device from individual to group...\n";
    $service->transferDeviceToGroup($device->id, ['sessionDeviceId' => $groupSession->id]);

    echo "✅ Transfer completed\n";

    // Step 4: Check activity log after transfer
    echo "\n🔍 Checking activity log after transfer...\n";
    $activities = $service->getActivityLogToDevice($device->id);

    echo "Activities count: " . $activities->count() . "\n\n";

    $timerIds = [];
    $deviceSessionKeys = [];

    if ($activities->count() > 0) {
        echo "📝 Activities after transfer:\n";
        foreach ($activities as $activity) {
            echo "  - Event: {$activity->event}\n";
            echo "    Log Name: {$activity->log_name}\n";
            echo "    Description: {$activity->description}\n";
            echo "    Created: {$activity->created_at}\n";

            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            if (isset($properties['timer_id'])) {
                $timerIds[] = $properties['timer_id'];
                echo "    Timer ID: {$properties['timer_id']}\n";
            }
            if (isset($properties['device_session_key'])) {
                $deviceSessionKeys[] = $properties['device_session_key'];
                echo "    Device Session Key: {$properties['device_session_key']}\n";
            }
            if (isset($properties['session_type'])) {
                echo "    Session Type: {$properties['session_type']}\n";
            }
            if (isset($properties['transfer_type'])) {
                echo "    Transfer Type: {$properties['transfer_type']}\n";
            }
            echo "    ---\n";
        }

        // Verify timer_id consistency
        echo "\n🎯 Timer ID Consistency Check:\n";
        $uniqueTimerIds = array_unique($timerIds);
        if (count($uniqueTimerIds) === 1) {
            $consistentTimerId = $uniqueTimerIds[0];
            if ($consistentTimerId === $originalTimerId) {
                echo "✅ Timer ID is consistent across all activities: {$consistentTimerId}\n";
                echo "✅ Timer ID matches original: {$originalTimerId}\n";
            } else {
                echo "❌ Timer ID changed after transfer:\n";
                echo "  Original: {$originalTimerId}\n";
                echo "  Current: {$consistentTimerId}\n";
            }
        } else {
            echo "❌ Timer ID is not consistent across activities:\n";
            foreach ($uniqueTimerIds as $timerId) {
                echo "  - {$timerId}\n";
            }
        }

        // Verify device_session_key consistency
        echo "\n🔑 Device Session Key Check:\n";
        $uniqueSessionKeys = array_unique($deviceSessionKeys);
        if (count($uniqueSessionKeys) === 1) {
            echo "✅ Device Session Key is consistent: {$uniqueSessionKeys[0]}\n";
        } else {
            echo "ℹ️ Multiple Device Session Keys (expected for transfers):\n";
            foreach ($uniqueSessionKeys as $key) {
                echo "  - {$key}\n";
            }
        }

    } else {
        echo "❌ No activities found after transfer\n";
    }

    // Step 5: Transfer back to individual
    echo "\n🔄 Transferring device back to individual session...\n";
    $service->transferBookedDeviceToSessionDevice($device->id, $daily->id);

    echo "✅ Transfer back completed\n";

    // Step 6: Final check
    echo "\n🔍 Final activity log check...\n";
    $finalActivities = $service->getActivityLogToDevice($device->id);

    echo "Final activities count: " . $finalActivities->count() . "\n";

    $finalTimerIds = [];
    foreach ($finalActivities as $activity) {
        $properties = is_string($activity->properties)
            ? json_decode($activity->properties, true)
            : ($activity->properties ?? []);

        if (isset($properties['timer_id'])) {
            $finalTimerIds[] = $properties['timer_id'];
        }
    }

    $uniqueFinalTimerIds = array_unique($finalTimerIds);
    if (count($uniqueFinalTimerIds) === 1 && $uniqueFinalTimerIds[0] === $originalTimerId) {
        echo "✅ Timer ID remains consistent after all transfers: {$uniqueFinalTimerIds[0]}\n";
    } else {
        echo "❌ Timer ID consistency broken after transfers\n";
    }

    DB::rollBack(); // Don't save test data
    echo "\n🔄 Rolled back test data\n";

} catch (Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Timer Transfer Consistency Test Complete ===\n";

?>
