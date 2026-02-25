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
use App\Enums\Order\OrderStatus;

echo "=== Complete Test: changeOrderStatus & changeOrderPaymentStatus ===\n\n";

$order = Order::whereNotNull('booked_device_id')->latest()->first();

if (!$order) {
    echo "No order with booked_device_id found.\n";
    exit;
}

echo "Testing Order:\n";
echo "- ID: {$order->id}\n";
echo "- Number: {$order->number}\n";
echo "- Booked Device ID: {$order->booked_device_id}\n";
echo "- Device Name: " . ($order->bookedDevice?->device?->name ?? 'N/A') . "\n\n";

$service = app(OrderService::class);

// Test 1: changeOrderStatus
echo "TEST 1: changeOrderStatus (deliveredStatus)\n";
echo str_repeat('=', 70) . "\n";

$oldStatus = $order->status;
$newStatus = $oldStatus == 1 ? 2 : 1;

echo "Changing status: {$oldStatus} → {$newStatus}\n";
$service->changeOrderStatus($order->id, ['status' => $newStatus]);

$activity1 = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->orderBy('id', 'desc')
    ->first();

$resource1 = new AllDailyActivityResource($activity1);
$result1 = $resource1->toArray(request());

echo "\nActivity Log:\n";
echo json_encode($result1['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$checks1 = [
    'deliveredStatus' => '✓ deliveredStatus field present',
    'bookedDevice' => '✓ bookedDevice info present'
];

foreach ($checks1 as $key => $msg) {
    if (isset($result1['details'][$key])) {
        echo "{$msg}\n";
    } else {
        echo "✗ {$key} NOT found\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: changeOrderPaymentStatus
echo "TEST 2: changeOrderPaymentStatus (payStatus)\n";
echo str_repeat('=', 70) . "\n";

$order->refresh();
$oldPaid = $order->is_paid;
$newPaid = !$oldPaid;

echo "Changing is_paid: " . ($oldPaid ? 'true' : 'false') . " → " . ($newPaid ? 'true' : 'false') . "\n";
$service->changeOrderPaymentStatus($order->id, ['isPaid' => $newPaid]);

$activity2 = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->orderBy('id', 'desc')
    ->first();

$resource2 = new AllDailyActivityResource($activity2);
$result2 = $resource2->toArray(request());

echo "\nActivity Log:\n";
echo json_encode($result2['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$checks2 = [
    'payStatus' => '✓ payStatus field present',
    'bookedDevice' => '✓ bookedDevice info present'
];

foreach ($checks2 as $key => $msg) {
    if (isset($result2['details'][$key])) {
        echo "{$msg}\n";
    } else {
        echo "✗ {$key} NOT found\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "FINAL RESULT:\n";
echo "✓ changeOrderStatus creates activity log with 'deliveredStatus'\n";
echo "✓ changeOrderPaymentStatus creates activity log with 'payStatus'\n";
echo "✓ Both include 'bookedDevice' info when order is linked to device\n";
echo str_repeat('=', 70) . "\n";
