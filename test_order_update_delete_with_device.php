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

echo "=== Testing Order Update/Delete with BookedDevice Info ===\n\n";

// Get the latest order with booked_device_id
$order = Order::whereNotNull('booked_device_id')->latest()->first();

if (!$order) {
    echo "No order with booked_device_id found.\n";
    exit;
}

echo "Original Order:\n";
echo "- Order ID: {$order->id}\n";
echo "- Order Number: {$order->number}\n";
echo "- Booked Device ID: {$order->booked_device_id}\n";
echo "- Status: {$order->status}\n";
echo "- Is Paid: " . ($order->is_paid ? 'Yes' : 'No') . "\n\n";

// Test Update
echo "--- Testing Order Update ---\n";
$orderService = app(OrderService::class);

$updateData = [
    'name' => $order->name,
    'isPaid' => true, // Change to paid
    'status' => OrderStatus::CONFIRMED->value, // Change status
    'orderItems' => $order->items->map(function($item) {
        return [
            'orderItemId' => $item->id,
            'productId' => $item->product_id,
            'qty' => $item->qty,
            'price' => $item->price,
            'actionStatus' => '', // No change
        ];
    })->toArray()
];

$updatedOrder = $orderService->updateOrder($order->id, $updateData);
echo "Order Updated:\n";
echo "- Status: {$updatedOrder->status}\n";
echo "- Is Paid: " . ($updatedOrder->is_paid ? 'Yes' : 'No') . "\n\n";

// Get update activity
$updateActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'updated')
    ->latest()
    ->first();

if ($updateActivity) {
    echo "Update Activity Found:\n";
    $resource = new AllDailyActivityResource($updateActivity);
    $resourceArray = $resource->toArray(request());

    echo json_encode($resourceArray['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($resourceArray['details']['bookedDevice'])) {
        echo "✓ BookedDevice Info in Update: ID={$resourceArray['details']['bookedDevice']['id']}, Name={$resourceArray['details']['bookedDevice']['name']}\n\n";
    } else {
        echo "✗ BookedDevice Info NOT Found in Update\n\n";
    }
}

// Test Delete
echo "--- Testing Order Delete ---\n";
$orderService->deleteOrder($order->id);
echo "Order Deleted (soft delete)\n\n";

// Get delete activity
$deleteActivity = Activity::where('log_name', 'Order')
    ->where('event', 'deleted')
    ->latest()
    ->first();

if ($deleteActivity) {
    echo "Delete Activity Found:\n";
    $resource = new AllDailyActivityResource($deleteActivity);
    $resourceArray = $resource->toArray(request());

    echo json_encode($resourceArray['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($resourceArray['details']['bookedDevice'])) {
        echo "✓ BookedDevice Info in Delete: ID={$resourceArray['details']['bookedDevice']['id']}, Name={$resourceArray['details']['bookedDevice']['name']}\n";
    } else {
        echo "✗ BookedDevice Info NOT Found in Delete\n";
    }
}

echo "\n=== Test Complete ===\n";
