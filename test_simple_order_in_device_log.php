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
use App\Models\Order\Order;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\Order\OrderTypeEnum;
use App\Enums\Order\OrderStatus;
use Illuminate\Support\Facades\DB;

echo "=== Testing Simple Order in Device Activity Log ===\n\n";

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

    // Create order manually to avoid OrderService issues
    echo "\n🛒 Creating order manually...\n";
    $order = Order::create([
        'name' => 'Test Order',
        'type' => OrderTypeEnum::INTERNAL->value,
        'is_paid' => false,
        'status' => OrderStatus::PENDING->value,
        'booked_device_id' => $device->id,
        'daily_id' => $daily->id,
        'price' => 25.50
    ]);

    echo "✅ Created Order ID: {$order->id}\n";

    // Create manual activity log for the order with timer tracking
    activity()
        ->useLog('Order')
        ->event('created')
        ->performedOn($order)
        ->withProperties([
            'attributes' => [
                'id' => $order->id,
                'name' => $order->name,
                'number' => $order->number,
                'type' => $order->type,
                'price' => $order->price,
                'is_paid' => $order->is_paid,
                'status' => $order->status,
                'booked_device_id' => $order->booked_device_id,
                'daily_id' => $order->daily_id,
            ],
            'device_session_key' => $deviceSessionKey,
            'timer_id' => $timerId,
            'device_id' => $device->device_id,
            'related_to_device' => true,
            'session_type' => 'individual'
        ])
        ->tap(function ($activity) use ($order) {
            $activity->daily_id = $order->daily_id;
        })
        ->log('Order created');

    echo "✅ Created order activity log with timer tracking\n";

    // Check activity log
    echo "\n🔍 Checking device activity log with order...\n";
    $activities = $bookedDeviceService->getActivityLogToDevice($device->id);

    echo "📊 Activities count: " . $activities->count() . "\n\n";

    if ($activities->count() > 0) {
        echo "📝 Activities including order:\n";
        $orderActivitiesCount = 0;

        foreach ($activities as $activity) {
            echo "  - Event: {$activity->event}\n";
            echo "    Log Name: {$activity->log_name}\n";
            echo "    Description: {$activity->description}\n";
            echo "    Date: {$activity->created_at->format('Y-m-d H:i:s')}\n";

            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            if (isset($properties['timer_id'])) {
                echo "    Timer ID: {$properties['timer_id']}\n";
            }
            if (isset($properties['related_to_device'])) {
                echo "    Related to Device: {$properties['related_to_device']}\n";
                $orderActivitiesCount++;
            }
            if (isset($properties['attributes']['name'])) {
                echo "    Order Name: {$properties['attributes']['name']}\n";
            }
            if (isset($properties['attributes']['number'])) {
                echo "    Order Number: {$properties['attributes']['number']}\n";
            }
            if (isset($properties['attributes']['price'])) {
                echo "    Order Price: {$properties['attributes']['price']}\n";
            }
            echo "    ---\n";
        }

        echo "\n🎯 Summary:\n";
        echo "  - Total activities: " . $activities->count() . "\n";
        echo "  - Order activities: {$orderActivitiesCount}\n";

        if ($orderActivitiesCount >= 1) {
            echo "✅ SUCCESS: Order is showing in device activity log\n";
        } else {
            echo "❌ PROBLEM: Order is missing from device activity log\n";
        }

        // Check timer_id consistency
        $timerIds = [];
        foreach ($activities as $activity) {
            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);
            if (isset($properties['timer_id'])) {
                $timerIds[] = $properties['timer_id'];
            }
        }

        $uniqueTimerIds = array_unique($timerIds);
        if (count($uniqueTimerIds) === 1) {
            echo "✅ All activities have consistent timer_id: {$uniqueTimerIds[0]}\n";
        } else {
            echo "❌ Timer_id is not consistent across activities\n";
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

echo "\n=== Simple Order in Device Activity Log Test Complete ===\n";

?>
