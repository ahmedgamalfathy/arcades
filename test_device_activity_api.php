<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Device Activity Log API ===\n\n";

// Get a booked device with orders
$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::whereHas('orders')->orderBy('id', 'desc')->first();

if (!$bookedDevice) {
    echo "No booked device with orders found! Using any device...\n";
    $bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::orderBy('id', 'desc')->first();
}

if (!$bookedDevice) {
    echo "No booked device found!\n";
    exit;
}

echo "BookedDevice ID: {$bookedDevice->id}\n";
echo "Device: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "Session: " . ($bookedDevice->sessionDevice?->name ?? 'N/A') . "\n";
echo "Orders count: " . $bookedDevice->orders->count() . "\n\n";

// Call the controller method
$controller = new App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController(
    new App\Services\Timer\SessionDeviceService(),
    new App\Services\Timer\DeviceTimerService(
        new App\Services\Timer\BookedDeviceService(),
        new App\Services\Timer\BookedDevicePauseService()
    ),
    new App\Services\Timer\BookedDeviceService()
);

$response = $controller->getActitvityLogToDevice($bookedDevice->id);
$responseData = $response->getData(true);

echo "API Response:\n";
echo json_encode($responseData['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n✓ Test completed!\n";
