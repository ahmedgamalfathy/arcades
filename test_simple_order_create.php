<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Simple Order Create Test ===\n\n";

// Create order directly
$order = App\Models\Order\Order::create([
    'name' => 'Direct Test',
    'type' => 1,
    'is_paid' => false,
    'status' => 2,
    'booked_device_id' => 21,
    'daily_id' => 2,
    'price' => 100,
]);

echo "Order created:\n";
echo "  ID: {$order->id}\n";
echo "  Name: {$order->name}\n";
echo "  BookedDevice ID: " . ($order->booked_device_id ?? 'NULL') . "\n";
echo "  Price: {$order->price}\n\n";

// Refresh from database
$order->refresh();

echo "After refresh:\n";
echo "  BookedDevice ID: " . ($order->booked_device_id ?? 'NULL') . "\n";
