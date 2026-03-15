<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Services\Order\OrderService;
use App\Services\Order\OrderItemService;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Daily\Daily;
use App\Models\Order\Order;
use App\Enums\SessionDevice\SessionDeviceEnum;
use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Enums\Order\OrderTypeEnum;
use App\Enums\Order\OrderStatus;
use Illuminate\Support\Facades\DB;

echo "=== Testing Individual to Group Transfer with Orders ===\n\n";

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

    $bookedDeviceService = new BookedDeviceService();

    // Step 1: Create individual session (type 0)
    echo "\n=== Step 1: Create Individual Session (Type 0) ===\n";

    $individualSession = SessionDevice::withoutEvents(function () use ($daily) {
        return SessionDevice::create([
            'name' => 'individual',
            'type' => SessionDeviceEnum::INDIVIDUAL->value, // Type 0
            'daily_id' => $daily->id
        ]);
    });

    $device = $bookedDeviceService->createBookedDeviceWithoutLog([
        'sessionDeviceId' => $individualSession->id,
        'deviceTypeId' => 1,
        'deviceId' => 1,
        'deviceTimeId' => 1,
        'startDateTime' => now(),
        'endDateTime' => null,
        'totalUsedSeconds' => 0,
        'status' => BookedDeviceEnum::ACTIVE->value,
    ]);

    echo "✅ Created Individual Session ID: {$individualSession->id} (Type: {$individualSession->type})\n";
    echo "✅ Created Device ID: {$device->id}\n";

    // Create initial activity log
    $timerId = 'timer_' . $device->device_id . '_' . $device->created_at->timestamp;
    $deviceSessionKey = 'device_' . $device->device_id . '_daily_' . $daily->id . '_' . now()->format('Y-m-d');

    activity()
        ->useLog('SessionDevice')
        ->event('created')
        ->performedOn($individualSession)
        ->withProperties([
            'attributes' => ['id' => $individualSession->id, 'name' => $individualSession->name, 'type' => $individualSession->type],
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
        ->tap(function ($activity) use ($individualSession) {
            $activity->daily_id = $individualSession->daily_id;
        })
        ->log('SessionDevice - Individual time created');

    echo "✅ Created initial activity log\n";

    // Step 2: Create order for individual session
    echo "\n=== Step 2: Create Order for Individual Session ===\n";

    $order1 = Order::create([
        'name' => 'Individual Order',
        'type' => OrderTypeEnum::INTERNAL->value,
        'is_paid' => false,
        'status' => OrderStatus::PENDING->value,
        'booked_device_id' => $device->id,
        'daily_id' => $daily->id,
        'price' => 25.00
    ]);

    // Log order with timer tracking
    activity()
        ->useLog('Order')
        ->event('created')
        ->performedOn($order1)
        ->withProperties([
            'attributes' => [
                'id' => $order1->id,
                'name' => $order1->name,
                'number' => $order1->number,
                'type' => $order1->type,
                'price' => $order1->price,
                'is_paid' => $order1->is_paid,
                'status' => $order1->status,
                'booked_device_id' => $order1->booked_device_id,
                'daily_id' => $order1->daily_id,
            ],
            'device_session_key' => $deviceSessionKey,
            'timer_id' => $timerId,
            'device_id' => $device->device_id,
            'related_to_device' => true,
            'session_type' => 'individual'
        ])
        ->tap(function ($activity) use ($order1) {
            $activity->daily_id = $order1->daily_id;
        })
        ->log('Order created for individual session');

    echo "✅ Created Order 1 ID: {$order1->id} ({$order1->number}) for Individual Session\n";

    // Step 3: Transfer to group session (type 1)
    echo "\n=== Step 3: Transfer to Group Session (Type 1) ===\n";

    $groupSession = SessionDevice::withoutEvents(function () use ($daily) {
        return SessionDevice::create([
            'name' => 'Test Group',
            'type' => SessionDeviceEnum::GROUP->value, // Type 1
            'daily_id' => $daily->id
        ]);
    });

    echo "✅ Created Group Session ID: {$groupSession->id} (Type: {$groupSession->type})\n";

    // Transfer device to group
    $bookedDeviceService->transferDeviceToGroup($device->id, ['sessionDeviceId' => $groupSession->id]);
    echo "✅ Transferred device to group session\n";

    // Step 4: Create order for group session
    echo "\n=== Step 4: Create Order for Group Session ===\n";

    $order2 = Order::create([
        'name' => 'Group Order',
        'type' => OrderTypeEnum::INTERNAL->value,
        'is_paid' => true,
        'status' => OrderStatus::DELIVERED->value,
        'booked_device_id' => $device->id,
        'daily_id' => $daily->id,
        'price' => 45.00
    ]);

    // Log order with timer tracking
    activity()
        ->useLog('Order')
        ->event('created')
        ->performedOn($order2)
        ->withProperties([
            'attributes' => [
                'id' => $order2->id,
                'name' => $order2->name,
                'number' => $order2->number,
                'type' => $order2->type,
                'price' => $order2->price,
                'is_paid' => $order2->is_paid,
                'status' => $order2->status,
                'booked_device_id' => $order2->booked_device_id,
                'daily_id' => $order2->daily_id,
            ],
            'device_session_key' => $deviceSessionKey,
            'timer_id' => $timerId,
            'device_id' => $device->device_id,
            'related_to_device' => true,
            'session_type' => 'group'
        ])
        ->tap(function ($activity) use ($order2) {
            $activity->daily_id = $order2->daily_id;
        })
        ->log('Order created for group session');

    echo "✅ Created Order 2 ID: {$order2->id} ({$order2->number}) for Group Session\n";

    // Step 5: Check final activity log
    echo "\n=== Step 5: Final Activity Log Check ===\n";

    $activities = $bookedDeviceService->getActivityLogToDevice($device->id);
    echo "📊 Total activities: " . $activities->count() . "\n\n";

    if ($activities->count() > 0) {
        echo "📝 Complete Activity Log (Chronological Order):\n";

        $sessionTypes = [];
        $orderCount = 0;
        $transferCount = 0;

        foreach ($activities as $index => $activity) {
            echo "  " . ($index + 1) . ". Event: {$activity->event}\n";
            echo "     Log Name: {$activity->log_name}\n";
            echo "     Description: {$activity->description}\n";
            echo "     Time: {$activity->created_at->format('H:i:s')}\n";

            $properties = is_string($activity->properties)
                ? json_decode($activity->properties, true)
                : ($activity->properties ?? []);

            if (isset($properties['timer_id'])) {
                echo "     Timer ID: {$properties['timer_id']}\n";
            }

            if (isset($properties['session_type'])) {
                $sessionTypes[] = $properties['session_type'];
                echo "     Session Type: {$properties['session_type']}\n";
            }

            if (isset($properties['transfer_type'])) {
                $transferCount++;
                echo "     Transfer Type: {$properties['transfer_type']}\n";
            }

            if (isset($properties['related_to_device'])) {
                $orderCount++;
                echo "     Order: {$properties['attributes']['name']} ({$properties['attributes']['number']})\n";
                echo "     Price: {$properties['attributes']['price']}\n";
            }

            echo "     ---\n";
        }

        echo "\n🎯 Summary:\n";
        echo "  - Total activities: " . $activities->count() . "\n";
        echo "  - Orders: {$orderCount}\n";
        echo "  - Transfers: {$transferCount}\n";
        echo "  - Session types seen: " . implode(', ', array_unique($sessionTypes)) . "\n";

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

        // Check if we have both individual and group activities
        $hasIndividual = in_array('individual', $sessionTypes);
        $hasGroup = in_array('group', $sessionTypes);

        if ($hasIndividual && $hasGroup) {
            echo "✅ Activities from both individual and group sessions are present\n";
        } else {
            echo "❌ Missing activities from one session type\n";
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

echo "\n=== Individual to Group Transfer Test Complete ===\n";

?>
