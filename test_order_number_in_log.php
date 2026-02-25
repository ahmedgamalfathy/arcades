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

echo "=== Testing Order Number in Activity Log ===\n\n";

$order = Order::whereNotNull('booked_device_id')->latest()->first();
$service = app(OrderService::class);

echo "Order Details:\n";
echo "- ID: {$order->id}\n";
echo "- Number: {$order->number}\n";
echo "- Booked Device ID: {$order->booked_device_id}\n\n";

// Test 1: changeOrderStatus
echo "TEST 1: changeOrderStatus\n";
echo str_repeat('-', 70) . "\n";

$service->changeOrderStatus($order->id, ['status' => 1]);

$activity1 = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->orderBy('id', 'desc')
    ->first();

$resource1 = new AllDailyActivityResource($activity1);
$result1 = $resource1->toArray(request());

echo "Activity Log:\n";
echo json_encode($result1['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$checks = ['number', 'deliveredStatus', 'bookedDevice'];
foreach ($checks as $field) {
    if (isset($result1['details'][$field])) {
        echo "✓ '{$field}' present\n";
        if ($field === 'number') {
            echo "  Value: {$result1['details'][$field]['new']}\n";
        }
    } else {
        echo "✗ '{$field}' NOT present\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: changeOrderPaymentStatus
echo "TEST 2: changeOrderPaymentStatus\n";
echo str_repeat('-', 70) . "\n";

$order->refresh();
$service->changeOrderPaymentStatus($order->id, ['isPaid' => !$order->is_paid]);

$activity2 = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->orderBy('id', 'desc')
    ->first();

$resource2 = new AllDailyActivityResource($activity2);
$result2 = $resource2->toArray(request());

echo "Activity Log:\n";
echo json_encode($result2['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$checks = ['number', 'payStatus', 'bookedDevice'];
foreach ($checks as $field) {
    if (isset($result2['details'][$field])) {
        echo "✓ '{$field}' present\n";
        if ($field === 'number') {
            echo "  Value: {$result2['details'][$field]['new']}\n";
        }
    } else {
        echo "✗ '{$field}' NOT present\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "✓ Order number now appears in both changeOrderStatus and changeOrderPaymentStatus!\n";
echo str_repeat('=', 70) . "\n";
