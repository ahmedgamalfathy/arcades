<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Order with BookedDevice via Service ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Get a booked device
$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::orderBy('id', 'desc')->first();

if (!$bookedDevice) {
    echo "No booked device found!\n";
    exit;
}

echo "BookedDevice ID: {$bookedDevice->id}\n";
echo "Device Name: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "Daily ID: " . ($bookedDevice->sessionDevice?->daily_id ?? 'N/A') . "\n\n";

// Get a product
$product = App\Models\Product\Product::first();

if (!$product) {
    echo "No product found!\n";
    exit;
}

echo "Product ID: {$product->id}\n";
echo "Product Name: {$product->name}\n\n";

// Create Order using service
echo "Step 1: Create Order via OrderService\n";

$orderService = new App\Services\Order\OrderService(
    new App\Services\Order\OrderItemService()
);

$data = [
    'name' => 'Test Order',
    'type' => 0, // INTERNAL (not 1!)
    'bookedDeviceId' => $bookedDevice->id,
    'dailyId' => $bookedDevice->sessionDevice?->daily_id ?? 2,
    'orderItems' => [
        [
            'productId' => $product->id,
            'qty' => 2,
            'price' => 25.00,
        ]
    ]
];

echo "Data to be sent:\n";
echo "  bookedDeviceId: {$data['bookedDeviceId']}\n";
echo "  dailyId: {$data['dailyId']}\n\n";

$order = $orderService->createOrder($data);

echo "✓ Order created (ID: {$order->id})\n";
echo "  Number: {$order->number}\n";
echo "  Price: {$order->price}\n";
echo "  BookedDevice ID: {$order->booked_device_id}\n\n";

// Check activity
$activity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Order\\Order')
    ->where('subject_id', $order->id)
    ->where('event', 'created')
    ->first();

if ($activity) {
    echo "Activity found (ID: {$activity->id})\n";
    $props = json_decode($activity->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Test API Response
echo str_repeat('=', 80) . "\n";
echo "=== API Response ===\n\n";

$dailyId = $order->daily_id;

$dailyRelatedActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('daily_id', $dailyId)
    ->get();

$dailyOwnActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Daily\\Daily')
    ->where('subject_id', $dailyId)
    ->get();

$activities = $dailyRelatedActivities->merge($dailyOwnActivities)
    ->sortByDesc('created_at')
    ->values()
    ->filter(function($act) use ($order) {
        return $act->subject_type === 'App\\Models\\Order\\Order' && $act->subject_id == $order->id;
    });

$userIds = $activities->pluck('causer_id')->unique()->filter();
$users = DB::connection('mysql')->table('users')
    ->whereIn('id', $userIds)
    ->pluck('name', 'id');

$allActivities = $activities->map(function ($activity) use ($users) {
    $activity->properties = json_decode($activity->properties, true);
    $activity->causerName = $users[$activity->causer_id] ?? null;
    $activity->children = [];
    return $activity;
});

foreach ($allActivities as $act) {
    $resource = new App\Http\Resources\ActivityLog\Test\AllDailyActivityResource($act);
    $response = $resource->toArray(request());

    echo "Order Activity:\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n✓ Test completed!\n";
