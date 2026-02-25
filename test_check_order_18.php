<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

$order = App\Models\Order\Order::find(18);

echo "Order ID: {$order->id}\n";
echo "BookedDevice ID: " . ($order->booked_device_id ?? 'NULL') . "\n";
echo "Name: {$order->name}\n";
echo "Price: {$order->price}\n";
