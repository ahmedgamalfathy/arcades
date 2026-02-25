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
use Illuminate\Http\Request;

echo "=== Testing Order with BookedDevice ===\n\n";

// Get a daily
$daily = Daily::latest()->first();
if (!$daily) {
    echo "No daily found. Please create a daily first.\n";
    exit;
}
echo "Using Daily ID: {$daily->id}\n";

// Get a booked device
$bookedDevice = BookedDevice::latest()->first();
if (!$bookedDevice) {
    echo "No booked device found. Please create a booked device first.\n";
    exit;
}
echo "Using BookedDevice ID: {$bookedDevice->id}\n";
echo "BookedDevice Name: " . ($bookedDevice->device?->name ?? 'N/A') . "\n\n";

// Get a product
$product = Product::first();
if (!$product) {
    echo "No product found. Please create a product first.\n";
    exit;
}
echo "Using Product: {$product->name} (ID: {$product->id})\n\n";

// Create Order with BookedDevice
echo "--- Creating INTERNAL Order with BookedDevice ---\n";
$orderService = app(OrderService::class);

$orderData = [
    'name' => 'Test Order with Device',
    'type' => OrderTypeEnum::INTERNAL->value, // 0 = INTERNAL
    'isPaid' => false,
    'status' => OrderStatus::PENDING->value,
    'bookedDeviceId' => $bookedDevice->id,
    'dailyId' => $daily->id,
    'orderItems' => [
        [
            'productId' => $product->id,
            'qty' => 2,
            'price' => 50.00,
        ]
    ]
];

echo "Order Data:\n";
echo "- Type: " . $orderData['type'] . " (INTERNAL)\n";
echo "- BookedDevice ID: " . $orderData['bookedDeviceId'] . "\n";
echo "- Daily ID: " . $orderData['dailyId'] . "\n\n";

$order = $orderService->createOrder($orderData);

echo "Order Created:\n";
echo "- Order ID: {$order->id}\n";
echo "- Order Number: {$order->number}\n";
echo "- Order Type: {$order->type}\n";
echo "- Booked Device ID: " . ($order->booked_device_id ?? 'NULL') . "\n";
echo "- Total Price: {$order->price}\n\n";

// Get activity log for this daily
echo "--- Fetching Activity Log ---\n";
$url = "http://127.0.0.1:8000/api/v1/admin/daily/{$daily->id}/activity-log";
echo "URL: {$url}\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);

    echo "Activity Log Response:\n";

    if (!empty($data['data'])) {
        foreach ($data['data'] as $activity) {
            if ($activity['model']['modelName'] === 'Order') {
                echo "\n=== Order Activity ===\n";
                echo "Event: {$activity['eventType']}\n";
                echo "User: {$activity['userName']}\n";
                echo "Time: {$activity['time']}\n";

                if (!empty($activity['details'])) {
                    echo "\nDetails:\n";
                    echo json_encode($activity['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

                    // Check if bookedDevice info is present
                    if (isset($activity['details']['bookedDevice'])) {
                        echo "\n✓ BookedDevice Info Found:\n";
                        echo "  - ID: {$activity['details']['bookedDevice']['id']}\n";
                        echo "  - Name: {$activity['details']['bookedDevice']['name']}\n";
                    } else {
                        echo "\n✗ BookedDevice Info NOT Found\n";
                    }
                }

                if (!empty($activity['children'])) {
                    $childCount = count($activity['children']);
                    echo "\nChildren ({$childCount} items):\n";
                    foreach ($activity['children'] as $child) {
                        echo "  - {$child['modelName']}: {$child['eventType']}\n";
                    }
                }
            }
        }
    } else {
        echo "No activities found.\n";
    }
} else {
    echo "Failed to fetch activity log. HTTP Code: {$httpCode}\n";
    echo "Response: {$response}\n";
}

echo "\n=== Test Complete ===\n";
