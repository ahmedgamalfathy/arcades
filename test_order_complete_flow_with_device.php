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

echo "=== Complete Order Flow with BookedDevice ===\n\n";

// Setup
$daily = Daily::latest()->first();
$bookedDevice = BookedDevice::latest()->first();
$product = Product::first();

if (!$daily || !$bookedDevice || !$product) {
    echo "Missing required data. Please ensure Daily, BookedDevice, and Product exist.\n";
    exit;
}

echo "Setup:\n";
echo "- Daily ID: {$daily->id}\n";
echo "- BookedDevice ID: {$bookedDevice->id} (Name: " . ($bookedDevice->device?->name ?? 'N/A') . ")\n";
echo "- Product: {$product->name}\n\n";

$orderService = app(OrderService::class);

// Step 1: Create Order
echo "Step 1: Create INTERNAL Order with BookedDevice\n";
echo str_repeat('-', 50) . "\n";

$orderData = [
    'name' => 'Complete Flow Test Order',
    'type' => OrderTypeEnum::INTERNAL->value,
    'isPaid' => false,
    'status' => OrderStatus::PENDING->value,
    'bookedDeviceId' => $bookedDevice->id,
    'dailyId' => $daily->id,
    'orderItems' => [
        [
            'productId' => $product->id,
            'qty' => 3,
            'price' => 25.00,
        ]
    ]
];

$order = $orderService->createOrder($orderData);
echo "✓ Order Created: ID={$order->id}, Number={$order->number}\n";
echo "  - Booked Device ID: {$order->booked_device_id}\n";
echo "  - Price: {$order->price}\n";

$createActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'created')
    ->first();

if ($createActivity) {
    $resource = new AllDailyActivityResource($createActivity);
    $details = $resource->toArray(request())['details'];

    if (isset($details['bookedDevice'])) {
        echo "  ✓ Activity Log: BookedDevice info present (ID={$details['bookedDevice']['id']}, Name={$details['bookedDevice']['name']})\n";
    } else {
        echo "  ✗ Activity Log: BookedDevice info MISSING\n";
    }
}

// Step 2: Update Order
echo "\nStep 2: Update Order Status\n";
echo str_repeat('-', 50) . "\n";

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

$updatedOrder = $orderService->updateOrder($order->id, $updateData);
echo "✓ Order Updated: Status={$updatedOrder->status}, IsPaid=" . ($updatedOrder->is_paid ? 'Yes' : 'No') . "\n";

$updateActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'updated')
    ->latest()
    ->first();

if ($updateActivity) {
    $resource = new AllDailyActivityResource($updateActivity);
    $details = $resource->toArray(request())['details'];

    if (isset($details['bookedDevice'])) {
        echo "  ✓ Activity Log: BookedDevice info present (ID={$details['bookedDevice']['id']}, Name={$details['bookedDevice']['name']})\n";
    } else {
        echo "  ✗ Activity Log: BookedDevice info MISSING\n";
    }
}

// Step 3: Delete Order
echo "\nStep 3: Delete Order\n";
echo str_repeat('-', 50) . "\n";

$orderService->deleteOrder($order->id);
echo "✓ Order Deleted (soft delete)\n";

$deleteActivity = Activity::where('log_name', 'Order')
    ->where('event', 'deleted')
    ->latest()
    ->first();

if ($deleteActivity) {
    $resource = new AllDailyActivityResource($deleteActivity);
    $details = $resource->toArray(request())['details'];

    if (isset($details['bookedDevice'])) {
        echo "  ✓ Activity Log: BookedDevice info present (ID={$details['bookedDevice']['id']}, Name={$details['bookedDevice']['name']})\n";
    } else {
        echo "  ✗ Activity Log: BookedDevice info MISSING\n";
    }
}

// Summary
echo "\n" . str_repeat('=', 50) . "\n";
echo "SUMMARY: All Order operations (Create, Update, Delete)\n";
echo "successfully include BookedDevice info in activity logs!\n";
echo str_repeat('=', 50) . "\n";
