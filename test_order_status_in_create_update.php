<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Switch to tenant database
config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Daily\Daily;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Product\Product;
use App\Enums\Order\OrderTypeEnum;
use App\Enums\Order\OrderStatus;
use App\Services\Order\OrderService;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;

echo "=== Testing deliveredStatus & payStatus in Create/Update ===\n\n";

// Setup
$daily = Daily::latest()->first();
$bookedDevice = BookedDevice::latest()->first();
$product = Product::first();

if (!$daily || !$bookedDevice || !$product) {
    echo "Missing required data.\n";
    exit;
}

$orderService = app(OrderService::class);

// Test 1: Create Order
echo "TEST 1: Create Order\n";
echo str_repeat('-', 70) . "\n";

$orderData = [
    'name' => 'Test Order Status Fields',
    'type' => OrderTypeEnum::INTERNAL->value,
    'isPaid' => false,
    'status' => OrderStatus::PENDING->value,
    'bookedDeviceId' => $bookedDevice->id,
    'dailyId' => $daily->id,
    'orderItems' => [
        [
            'productId' => $product->id,
            'qty' => 1,
            'price' => 15.00,
        ]
    ]
];

echo "Creating Order with:\n";
echo "- isPaid: false\n";
echo "- status: " . OrderStatus::PENDING->value . " (PENDING)\n\n";

$order = $orderService->createOrder($orderData);
echo "Order Created: ID={$order->id}\n\n";

$createActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'created')
    ->first();

if ($createActivity) {
    $resource = new AllDailyActivityResource($createActivity);
    $result = $resource->toArray(request());

    echo "Activity Log Details:\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // Check fields
    $requiredFields = ['deliveredStatus', 'payStatus'];
    foreach ($requiredFields as $field) {
        if (isset($result['details'][$field])) {
            echo "✓ '{$field}' found in CREATE\n";
            echo "  old: " . json_encode($result['details'][$field]['old']) . "\n";
            echo "  new: " . json_encode($result['details'][$field]['new']) . "\n";
        } else {
            echo "✗ '{$field}' NOT found in CREATE\n";
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: Update Order
echo "TEST 2: Update Order\n";
echo str_repeat('-', 70) . "\n";

$updateData = [
    'name' => $order->name,
    'isPaid' => true,
    'status' => OrderStatus::CONFIRMED->value,
    'orderItems' => $order->items->map(function($item) {
        return [
            'orderItemId' => $item->id,
            'productId' => $item->product_id,
            'qty' => $item->qty,
            'price' => $item->price,
            'actionStatus' => '',
        ];
    })->toArray()
];

echo "Updating Order:\n";
echo "- isPaid: false → true\n";
echo "- status: " . OrderStatus::PENDING->value . " → " . OrderStatus::CONFIRMED->value . "\n\n";

$orderService->updateOrder($order->id, $updateData);
echo "Order Updated\n\n";

$updateActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'updated')
    ->latest()
    ->first();

if ($updateActivity) {
    $resource = new AllDailyActivityResource($updateActivity);
    $result = $resource->toArray(request());

    echo "Activity Log Details:\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // Check fields
    $requiredFields = ['deliveredStatus', 'payStatus'];
    foreach ($requiredFields as $field) {
        if (isset($result['details'][$field])) {
            echo "✓ '{$field}' found in UPDATE\n";
            echo "  old: " . json_encode($result['details'][$field]['old']) . "\n";
            echo "  new: " . json_encode($result['details'][$field]['new']) . "\n";
        } else {
            echo "✗ '{$field}' NOT found in UPDATE\n";
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "SUMMARY:\n";
echo "- deliveredStatus and payStatus should appear in both CREATE and UPDATE\n";
echo "- CREATE shows: old='' (empty), new=initial value\n";
echo "- UPDATE shows: old=previous value, new=new value (only if changed)\n";
echo str_repeat('=', 70) . "\n";
