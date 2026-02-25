<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Order Update Issue ===\n\n";

// Create order with booked_device_id
$order = App\Models\Order\Order::create([
    'name' => 'Test Update',
    'type' => 1,
    'is_paid' => false,
    'status' => 2,
    'booked_device_id' => 21,
    'daily_id' => 2,
    'price' => 50,
]);

echo "Order created:\n";
echo "  ID: {$order->id}\n";
echo "  BookedDevice ID: {$order->booked_device_id}\n";
echo "  Price: {$order->price}\n\n";

// Update only price (like in OrderService)
$order->update([
    'price' => 100,
]);

echo "After update (price only):\n";
echo "  BookedDevice ID: " . ($order->booked_device_id ?? 'NULL') . "\n";
echo "  Price: {$order->price}\n\n";

// Refresh from database
$order->refresh();

echo "After refresh:\n";
echo "  BookedDevice ID: " . ($order->booked_device_id ?? 'NULL') . "\n";
echo "  Price: {$order->price}\n";
