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

echo "=== Testing Order Status Names in Activity Log ===\n\n";

// Get an order
$order = Order::latest()->first();

if (!$order) {
    echo "No order found.\n";
    exit;
}

echo "Order Details:\n";
echo "- Order ID: {$order->id}\n";
echo "- Order Number: {$order->number}\n";
echo "- Current Status: {$order->status}\n";
echo "- Current Is Paid: " . ($order->is_paid ? 'Yes' : 'No') . "\n\n";

// Update order to change status and payment
echo "--- Updating Order Status and Payment ---\n";
$orderService = app(OrderService::class);

$updateData = [
    'name' => $order->name,
    'isPaid' => !$order->is_paid, // Toggle payment status
    'status' => $order->status == OrderStatus::PENDING->value ? OrderStatus::CONFIRMED->value : OrderStatus::PENDING->value, // Toggle status
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
echo "Order Updated:\n";
echo "- New Status: {$updatedOrder->status}\n";
echo "- New Is Paid: " . ($updatedOrder->is_paid ? 'Yes' : 'No') . "\n\n";

// Get the activity log
$activity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'updated')
    ->latest()
    ->first();

if (!$activity) {
    echo "No activity log found.\n";
    exit;
}

echo "--- Activity Log Details ---\n";
$resource = new AllDailyActivityResource($activity);
$resourceArray = $resource->toArray(request());

echo json_encode($resourceArray['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Check field names
echo "--- Checking Field Names ---\n";
if (isset($resourceArray['details']['deliveredStatus'])) {
    echo "✓ 'deliveredStatus' found (was 'status')\n";
    echo "  Old: {$resourceArray['details']['deliveredStatus']['old']}\n";
    echo "  New: {$resourceArray['details']['deliveredStatus']['new']}\n";
} else {
    echo "✗ 'deliveredStatus' NOT found\n";
}

if (isset($resourceArray['details']['payStatus'])) {
    echo "✓ 'payStatus' found (was 'isPaid')\n";
    echo "  Old: " . ($resourceArray['details']['payStatus']['old'] ? 'Paid' : 'Not Paid') . "\n";
    echo "  New: " . ($resourceArray['details']['payStatus']['new'] ? 'Paid' : 'Not Paid') . "\n";
} else {
    echo "✗ 'payStatus' NOT found\n";
}

echo "\n=== Test Complete ===\n";
