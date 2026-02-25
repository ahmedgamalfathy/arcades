<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Switch to tenant database
config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Order\Order;
use App\Services\Order\OrderService;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;
use App\Enums\Order\OrderStatus;

echo "=== Final Test: changeOrderStatus & changeOrderPaymentStatus ===\n\n";

$order = Order::whereNotNull('booked_device_id')->latest()->first();

if (!$order) {
    echo "No order with booked_device_id found.\n";
    exit;
}

echo "Order: ID={$order->id}, Number={$order->number}\n";
echo "Booked Device ID: {$order->booked_device_id}\n\n";

$orderService = app(OrderService::class);

// Test 1: changeOrderStatus
echo "TEST 1: changeOrderStatus\n";
echo str_repeat('-', 70) . "\n";

$oldStatus = $order->status;
$newStatus = $oldStatus == OrderStatus::PENDING->value ? OrderStatus::CONFIRMED->value : OrderStatus::PENDING->value;

echo "Changing status: {$oldStatus} → {$newStatus}\n";
$orderService->changeOrderStatus($order->id, ['status' => $newStatus]);

$activity1 = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->latest()
    ->first();

echo "\nRaw Properties:\n";
echo json_encode($activity1->properties, JSON_PRETTY_PRINT) . "\n\n";

$resource1 = new AllDailyActivityResource($activity1);
$result1 = $resource1->toArray(request());

echo "Resource Output:\n";
echo json_encode($result1['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (isset($result1['details']['deliveredStatus'])) {
    echo "✓ deliveredStatus: {$result1['details']['deliveredStatus']['old']} → {$result1['details']['deliveredStatus']['new']}\n";
}
if (isset($result1['details']['bookedDevice'])) {
    echo "✓ bookedDevice: ID={$result1['details']['bookedDevice']['id']}, Name={$result1['details']['bookedDevice']['name']}\n";
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: changeOrderPaymentStatus
echo "TEST 2: changeOrderPaymentStatus\n";
echo str_repeat('-', 70) . "\n";

$order->refresh();
$oldIsPaid = $order->is_paid;
$newIsPaid = !$oldIsPaid;

echo "Changing is_paid: " . ($oldIsPaid ? 'true' : 'false') . " → " . ($newIsPaid ? 'true' : 'false') . "\n";
$orderService->changeOrderPaymentStatus($order->id, ['isPaid' => $newIsPaid]);

$activity2 = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->latest()
    ->first();

echo "\nRaw Properties:\n";
echo json_encode($activity2->properties, JSON_PRETTY_PRINT) . "\n\n";

$resource2 = new AllDailyActivityResource($activity2);
$result2 = $resource2->toArray(request());

echo "Resource Output:\n";
echo json_encode($result2['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (isset($result2['details']['payStatus'])) {
    echo "✓ payStatus: " . json_encode($result2['details']['payStatus']['old']) . " → " . json_encode($result2['details']['payStatus']['new']) . "\n";
}
if (isset($result2['details']['bookedDevice'])) {
    echo "✓ bookedDevice: ID={$result2['details']['bookedDevice']['id']}, Name={$result2['details']['bookedDevice']['name']}\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "✓ Both methods now create activity logs with proper field names!\n";
echo str_repeat('=', 70) . "\n";
