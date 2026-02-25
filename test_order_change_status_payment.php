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

echo "=== Testing changeOrderStatus & changeOrderPaymentStatus Logging ===\n\n";

// Get an order
$order = Order::latest()->first();

if (!$order) {
    echo "No order found.\n";
    exit;
}

echo "Order Details:\n";
echo "- ID: {$order->id}\n";
echo "- Number: {$order->number}\n";
echo "- Current Status: {$order->status}\n";
echo "- Current Is Paid: " . ($order->is_paid ? 'Yes' : 'No') . "\n";
echo "- Booked Device ID: " . ($order->booked_device_id ?? 'NULL') . "\n\n";

$orderService = app(OrderService::class);

// Test 1: Change Order Status
echo "TEST 1: Change Order Status (changeOrderStatus)\n";
echo str_repeat('-', 70) . "\n";

$activitiesCountBefore = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->count();

echo "Activities BEFORE: {$activitiesCountBefore}\n";

$newStatus = $order->status == OrderStatus::PENDING->value ? OrderStatus::CONFIRMED->value : OrderStatus::PENDING->value;
echo "Changing status: {$order->status} → {$newStatus}\n\n";

$orderService->changeOrderStatus($order->id, ['status' => $newStatus]);

$activitiesCountAfter = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->count();

echo "Activities AFTER: {$activitiesCountAfter}\n";

if ($activitiesCountAfter > $activitiesCountBefore) {
    echo "✓ Activity log created!\n\n";

    $activity = Activity::where('subject_type', 'App\Models\Order\Order')
        ->where('subject_id', $order->id)
        ->latest()
        ->first();

    $resource = new AllDailyActivityResource($activity);
    $result = $resource->toArray(request());

    echo "Activity Details:\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($result['details']['deliveredStatus'])) {
        echo "✓ 'deliveredStatus' found in log\n";
    }
    if (isset($result['details']['bookedDevice'])) {
        echo "✓ 'bookedDevice' info found in log\n";
    }
} else {
    echo "✗ No activity log created\n";
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: Change Payment Status
echo "TEST 2: Change Payment Status (changeOrderPaymentStatus)\n";
echo str_repeat('-', 70) . "\n";

$activitiesCountBefore = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->count();

echo "Activities BEFORE: {$activitiesCountBefore}\n";

$order->refresh();
$newIsPaid = !$order->is_paid;
echo "Changing is_paid: " . ($order->is_paid ? 'Yes' : 'No') . " → " . ($newIsPaid ? 'Yes' : 'No') . "\n\n";

$orderService->changeOrderPaymentStatus($order->id, ['isPaid' => $newIsPaid]);

$activitiesCountAfter = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->count();

echo "Activities AFTER: {$activitiesCountAfter}\n";

if ($activitiesCountAfter > $activitiesCountBefore) {
    echo "✓ Activity log created!\n\n";

    $activity = Activity::where('subject_type', 'App\Models\Order\Order')
        ->where('subject_id', $order->id)
        ->latest()
        ->first();

    $resource = new AllDailyActivityResource($activity);
    $result = $resource->toArray(request());

    echo "Activity Details:\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($result['details']['payStatus'])) {
        echo "✓ 'payStatus' found in log\n";
    }
    if (isset($result['details']['bookedDevice'])) {
        echo "✓ 'bookedDevice' info found in log\n";
    }
} else {
    echo "✗ No activity log created\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "SUMMARY:\n";
echo "- changeOrderStatus should create activity log with 'deliveredStatus'\n";
echo "- changeOrderPaymentStatus should create activity log with 'payStatus'\n";
echo "- Both should include 'bookedDevice' info if order is linked to device\n";
echo str_repeat('=', 70) . "\n";
