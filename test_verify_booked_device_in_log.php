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

echo "=== Verification: BookedDevice Info in Order Activity Log ===\n\n";

// Setup
$daily = Daily::latest()->first();
$bookedDevice = BookedDevice::latest()->first();
$product = Product::first();

if (!$daily || !$bookedDevice || !$product) {
    echo "Missing required data.\n";
    exit;
}

echo "Test Setup:\n";
echo "- Daily ID: {$daily->id}\n";
echo "- BookedDevice ID: {$bookedDevice->id}\n";
echo "- BookedDevice Name: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "- Product: {$product->name}\n";
echo str_repeat('=', 70) . "\n\n";

$orderService = app(OrderService::class);

// Test 1: Create Order with BookedDevice
echo "TEST 1: Create Order with BookedDevice\n";
echo str_repeat('-', 70) . "\n";

$orderData = [
    'name' => 'Verification Test Order',
    'type' => OrderTypeEnum::INTERNAL->value,
    'isPaid' => false,
    'status' => OrderStatus::PENDING->value,
    'bookedDeviceId' => $bookedDevice->id,
    'dailyId' => $daily->id,
    'orderItems' => [
        [
            'productId' => $product->id,
            'qty' => 1,
            'price' => 10.00,
        ]
    ]
];

$order = $orderService->createOrder($orderData);
echo "Order Created: ID={$order->id}, Number={$order->number}\n";
echo "booked_device_id in database: {$order->booked_device_id}\n\n";

$createActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'created')
    ->first();

if ($createActivity) {
    $resource = new AllDailyActivityResource($createActivity);
    $result = $resource->toArray(request());

    echo "Activity Log (CREATE):\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($result['details']['bookedDevice'])) {
        echo "✓ PASS: bookedDevice info found\n";
        echo "  - ID: {$result['details']['bookedDevice']['id']}\n";
        echo "  - Name: {$result['details']['bookedDevice']['name']}\n";
    } else {
        echo "✗ FAIL: bookedDevice info NOT found\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: Update Order
echo "TEST 2: Update Order (change deliveredStatus and payStatus)\n";
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

    echo "Activity Log (UPDATE):\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    $checks = [
        'deliveredStatus' => 'deliveredStatus field',
        'payStatus' => 'payStatus field',
        'bookedDevice' => 'bookedDevice info'
    ];

    foreach ($checks as $key => $label) {
        if (isset($result['details'][$key])) {
            echo "✓ PASS: {$label} found\n";
            if ($key === 'bookedDevice') {
                echo "  - ID: {$result['details'][$key]['id']}\n";
                echo "  - Name: {$result['details'][$key]['name']}\n";
            }
        } else {
            echo "✗ FAIL: {$label} NOT found\n";
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 3: Delete Order
echo "TEST 3: Delete Order\n";
echo str_repeat('-', 70) . "\n";

$orderService->deleteOrder($order->id);
echo "Order Deleted\n\n";

$deleteActivity = Activity::where('log_name', 'Order')
    ->where('event', 'deleted')
    ->latest()
    ->first();

if ($deleteActivity) {
    $resource = new AllDailyActivityResource($deleteActivity);
    $result = $resource->toArray(request());

    echo "Activity Log (DELETE):\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($result['details']['bookedDevice'])) {
        echo "✓ PASS: bookedDevice info found\n";
        echo "  - ID: {$result['details']['bookedDevice']['id']}\n";
        echo "  - Name: {$result['details']['bookedDevice']['name']}\n";
    } else {
        echo "✗ FAIL: bookedDevice info NOT found\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "VERIFICATION COMPLETE\n";
echo str_repeat('=', 70) . "\n";
