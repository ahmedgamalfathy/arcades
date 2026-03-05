<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Daily\Daily;
use App\Models\Product\Product;
use App\Enums\Order\OrderTypeEnum;
use App\Enums\Order\OrderStatus;
use App\Services\Order\OrderService;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;

echo "=== Testing Order 'name' in All Operations ===\n\n";

$daily = Daily::latest()->first();
$product = Product::first();
$service = app(OrderService::class);

// Test 1: CREATE
echo "TEST 1: CREATE Order\n";
echo str_repeat('-', 70) . "\n";

$orderData = [
    'name' => 'Test Order Name',
    'type' => OrderTypeEnum::EXTERNAL->value,
    'isPaid' => false,
    'status' => OrderStatus::PENDING->value,
    'dailyId' => $daily->id,
    'orderItems' => [
        [
            'productId' => $product->id,
            'qty' => 1,
            'price' => 10.00,
        ]
    ]
];

$order = $service->createOrder($orderData);
echo "Order Created: ID={$order->id}, Name={$order->name}\n\n";

$createActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'created')
    ->first();

$resource = new AllDailyActivityResource($createActivity);
$result = $resource->toArray(request());

echo "CREATE Activity Log:\n";
echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (isset($result['details']['name'])) {
    echo "✓ name present: old=\"{$result['details']['name']['old']}\", new=\"{$result['details']['name']['new']}\"\n";
    if ($result['details']['name']['old'] === '') {
        echo "  ✓ old is empty (correct for CREATE)\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: UPDATE (change name)
echo "TEST 2: UPDATE Order (change name)\n";
echo str_repeat('-', 70) . "\n";

$updateData = [
    'name' => 'Updated Order Name',
    'isPaid' => false,
    'status' => OrderStatus::PENDING->value,
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

$service->updateOrder($order->id, $updateData);
echo "Order Updated: Name changed to 'Updated Order Name'\n\n";

$updateActivity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'updated')
    ->latest()
    ->first();

$resource = new AllDailyActivityResource($updateActivity);
$result = $resource->toArray(request());

echo "UPDATE Activity Log:\n";
echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (isset($result['details']['name'])) {
    echo "✓ name present: old=\"{$result['details']['name']['old']}\", new=\"{$result['details']['name']['new']}\"\n";
    if ($result['details']['name']['old'] !== '' && $result['details']['name']['new'] !== $result['details']['name']['old']) {
        echo "  ✓ Both old and new values shown (name changed)\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 3: DELETE
echo "TEST 3: DELETE Order\n";
echo str_repeat('-', 70) . "\n";

$service->deleteOrder($order->id);
echo "Order Deleted\n\n";

$deleteActivity = Activity::where('log_name', 'Order')
    ->where('event', 'deleted')
    ->latest()
    ->first();

$resource = new AllDailyActivityResource($deleteActivity);
$result = $resource->toArray(request());

echo "DELETE Activity Log:\n";
echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (isset($result['details']['name'])) {
    echo "✓ name present: old=\"{$result['details']['name']['old']}\", new=\"{$result['details']['name']['new']}\"\n";
    if ($result['details']['name']['old'] === '') {
        echo "  ✓ old is empty (correct for DELETE)\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "SUMMARY:\n";
echo "- CREATE: name with old=\"\", new=\"value\"\n";
echo "- UPDATE: name with old=\"old_value\", new=\"new_value\" (if changed)\n";
echo "- DELETE: name with old=\"\", new=\"value\"\n";
echo str_repeat('=', 70) . "\n";
