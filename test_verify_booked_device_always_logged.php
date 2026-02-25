<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Order\Order;
use App\Services\Order\OrderService;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;

echo "=== Verifying bookedDevice is Always Logged ===\n\n";

$service = app(OrderService::class);

// Test 1: Order WITH booked_device_id
echo "TEST 1: Order WITH booked_device_id\n";
echo str_repeat('-', 70) . "\n";

$orderWithDevice = Order::whereNotNull('booked_device_id')->latest()->first();

if ($orderWithDevice) {
    echo "Order ID: {$orderWithDevice->id}\n";
    echo "Booked Device ID: {$orderWithDevice->booked_device_id}\n\n";

    $service->changeOrderStatus($orderWithDevice->id, ['status' => 1]);

    $activity = Activity::where('subject_type', 'App\Models\Order\Order')
        ->where('subject_id', $orderWithDevice->id)
        ->orderBy('id', 'desc')
        ->first();

    echo "Raw Properties:\n";
    echo json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n\n";

    $resource = new AllDailyActivityResource($activity);
    $result = $resource->toArray(request());

    echo "Resource Output:\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($result['details']['bookedDevice'])) {
        echo "✓ bookedDevice present:\n";
        echo "  - ID: {$result['details']['bookedDevice']['id']}\n";
        echo "  - Name: {$result['details']['bookedDevice']['name']}\n";
    } else {
        echo "✗ bookedDevice NOT present\n";
    }
} else {
    echo "No order with booked_device_id found\n";
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: Order WITHOUT booked_device_id
echo "TEST 2: Order WITHOUT booked_device_id\n";
echo str_repeat('-', 70) . "\n";

$orderWithoutDevice = Order::whereNull('booked_device_id')->latest()->first();

if ($orderWithoutDevice) {
    echo "Order ID: {$orderWithoutDevice->id}\n";
    echo "Booked Device ID: NULL\n\n";

    $service->changeOrderStatus($orderWithoutDevice->id, ['status' => 2]);

    $activity = Activity::where('subject_type', 'App\Models\Order\Order')
        ->where('subject_id', $orderWithoutDevice->id)
        ->orderBy('id', 'desc')
        ->first();

    echo "Raw Properties:\n";
    echo json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n\n";

    $resource = new AllDailyActivityResource($activity);
    $result = $resource->toArray(request());

    echo "Resource Output:\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($result['details']['bookedDevice'])) {
        echo "✓ bookedDevice present (should NOT be here)\n";
    } else {
        echo "✓ bookedDevice NOT present (correct for orders without device)\n";
    }
} else {
    echo "No order without booked_device_id found\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "SUMMARY:\n";
echo "- Orders WITH booked_device_id should show bookedDevice info\n";
echo "- Orders WITHOUT booked_device_id should NOT show bookedDevice info\n";
echo str_repeat('=', 70) . "\n";
