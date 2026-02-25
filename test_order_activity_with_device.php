<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Switch to tenant database
config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Order\Order;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;

echo "=== Testing Order Activity with BookedDevice Info ===\n\n";

// Get the latest order with booked_device_id
$order = Order::whereNotNull('booked_device_id')->latest()->first();

if (!$order) {
    echo "No order with booked_device_id found.\n";
    exit;
}

echo "Order Details:\n";
echo "- Order ID: {$order->id}\n";
echo "- Order Number: {$order->number}\n";
echo "- Order Type: {$order->type}\n";
echo "- Booked Device ID: {$order->booked_device_id}\n";
echo "- Price: {$order->price}\n\n";

// Get activity log for this order
$activity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'created')
    ->latest()
    ->first();

if (!$activity) {
    echo "No activity log found for this order.\n";
    exit;
}

echo "Activity Log Found:\n";
echo "- Activity ID: {$activity->id}\n";
echo "- Event: {$activity->event}\n";
echo "- Log Name: {$activity->log_name}\n\n";

// Process through Resource
$resource = new AllDailyActivityResource($activity);
$resourceArray = $resource->toArray(request());

echo "Resource Output:\n";
echo json_encode($resourceArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Check if bookedDevice info is present
if (isset($resourceArray['details']['bookedDevice'])) {
    echo "✓ SUCCESS: BookedDevice Info Found in Activity!\n";
    echo "  - BookedDevice ID: {$resourceArray['details']['bookedDevice']['id']}\n";
    echo "  - BookedDevice Name: {$resourceArray['details']['bookedDevice']['name']}\n";
} else {
    echo "✗ FAILED: BookedDevice Info NOT Found in Activity\n";
    echo "\nDetails structure:\n";
    print_r($resourceArray['details']);
}

echo "\n=== Test Complete ===\n";
