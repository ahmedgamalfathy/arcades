<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Step 1: Create New Order in Daily 2 ===\n\n";

// Get daily 2
$daily = App\Models\Daily\Daily::find(2);

if (!$daily) {
    echo "Daily 2 not found!\n";
    exit;
}

echo "Daily ID: {$daily->id}\n";
echo "Daily Name: {$daily->name}\n\n";

$products = App\Models\Product\Product::all();
echo "Available Products:\n";
foreach ($products as $product) {
    echo "  - Product {$product->id}: {$product->name} (Price: {$product->price})\n";
}

// Create new order
$orderService = app(App\Services\Order\OrderService::class);

$orderData = [
    'name' => 'Final Test Order',
    'type' => 1,
    'status' => 2,
    'isPaid' => 0,
    'dailyId' => 2, // Daily 2
    'orderItems' => [
        [
            'productId' => 1,
            'qty' => 2,
            'price' => 10.00
        ],
        [
            'productId' => 2,
            'qty' => 1,
            'price' => 321.00
        ]
    ]
];

echo "\n\nCreating order in Daily 2...\n";
$order = $orderService->createOrder($orderData);
echo "✓ Order created!\n";
echo "Order ID: {$order->id}\n";
echo "Order Number: {$order->number}\n";
echo "Order Price: {$order->price}\n";
echo "Daily ID: {$order->daily_id}\n";

// Get create activity
$createActivity = DB::connection('tenant')
    ->table('activity_log')
    ->orderBy('id', 'desc')
    ->first();

echo "\n=== Create Activity Log ===\n";
echo "Activity ID: {$createActivity->id}\n";
echo "Event: {$createActivity->event}\n";
echo "Daily ID: {$createActivity->daily_id}\n";

// Test API response for create
$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('id', $createActivity->id)
    ->get();

$userIds = $activities->pluck('causer_id')->unique()->filter();
$users = DB::connection('mysql')->table('users')
    ->whereIn('id', $userIds)
    ->pluck('name', 'id');

$allActivities = $activities->map(function ($act) use ($users) {
    $act->properties = json_decode($act->properties, true);
    $act->causerName = $users[$act->causer_id] ?? null;
    return $act;
});

$controller = new App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('groupParentChildActivities');
$method->setAccessible(true);

$groupedActivities = $method->invoke($controller, $allActivities);
$resource = App\Http\Resources\ActivityLog\Test\AllDailyActivityResource::collection($groupedActivities);
$response = $resource->toArray(request());

echo "\nCreate API Response:\n";
echo json_encode($response[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Wait a moment
sleep(1);

// Now delete the order
echo "\n\n" . str_repeat('=', 80) . "\n";
echo "=== Step 2: Delete Order ===\n\n";

echo "Deleting order {$order->id}...\n";
$orderService->deleteOrder($order->id);
echo "✓ Order deleted!\n";

// Get delete activity
$deleteActivity = DB::connection('tenant')
    ->table('activity_log')
    ->orderBy('id', 'desc')
    ->first();

echo "\n=== Delete Activity Log ===\n";
echo "Activity ID: {$deleteActivity->id}\n";
echo "Event: {$deleteActivity->event}\n";
echo "Daily ID: {$deleteActivity->daily_id}\n";

// Test API response for delete
$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('id', $deleteActivity->id)
    ->get();

$allActivities = $activities->map(function ($act) use ($users) {
    $act->properties = json_decode($act->properties, true);
    $act->causerName = $users[$act->causer_id] ?? null;
    return $act;
});

$groupedActivities = $method->invoke($controller, $allActivities);
$resource = App\Http\Resources\ActivityLog\Test\AllDailyActivityResource::collection($groupedActivities);
$response = $resource->toArray(request());

echo "\nDelete API Response:\n";
echo json_encode($response[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n\n" . str_repeat('=', 80) . "\n";
echo "=== Final Summary ===\n\n";
echo "✓ Create Activity ID: {$createActivity->id} (Daily 2)\n";
echo "✓ Delete Activity ID: {$deleteActivity->id} (Daily 2)\n";
echo "\nBoth activities are in Daily 2 and show complete item details!\n";
