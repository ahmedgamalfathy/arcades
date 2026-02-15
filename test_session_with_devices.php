<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing SessionDevice with BookedDevices ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Check available devices
$devices = App\Models\Device\Device::limit(3)->get();
echo "Available Devices:\n";
foreach ($devices as $device) {
    echo "  Device {$device->id}: {$device->name}\n";
}

if ($devices->isEmpty()) {
    echo "No devices found!\n";
    exit;
}

// Check device types
$deviceTypes = App\Models\Device\DeviceType\DeviceType::limit(3)->get();
echo "\nAvailable Device Types:\n";
foreach ($deviceTypes as $type) {
    echo "  Type {$type->id}: {$type->name}\n";
}

// Check device times
$deviceTimes = App\Models\Device\DeviceTime\DeviceTime::limit(3)->get();
echo "\nAvailable Device Times:\n";
foreach ($deviceTimes as $time) {
    echo "  Time {$time->id}: {$time->name} (Rate: {$time->rate})\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "=== Step 1: Create Session ===\n\n";

$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'name' => 'جلسة مع أجهزة',
    'daily_id' => 2,
    'type' => 1, // GROUP
]);

echo "✓ Session created: {$session->name} (ID: {$session->id})\n";

echo "\n" . str_repeat('=', 80) . "\n";
echo "=== Step 2: Add BookedDevices to Session ===\n\n";

if ($devices->count() >= 2 && $deviceTypes->isNotEmpty() && $deviceTimes->isNotEmpty()) {
    $bookedDevice1 = App\Models\Timer\BookedDevice\BookedDevice::create([
        'session_device_id' => $session->id,
        'device_id' => $devices[0]->id,
        'device_type_id' => $deviceTypes[0]->id,
        'device_time_id' => $deviceTimes[0]->id,
        'start_date_time' => \Carbon\Carbon::now(),
        'status' => 1, // ACTIVE
    ]);

    echo "✓ BookedDevice 1 created: Device {$devices[0]->name}\n";

    $bookedDevice2 = App\Models\Timer\BookedDevice\BookedDevice::create([
        'session_device_id' => $session->id,
        'device_id' => $devices[1]->id,
        'device_type_id' => $deviceTypes[0]->id,
        'device_time_id' => $deviceTimes[0]->id,
        'start_date_time' => \Carbon\Carbon::now(),
        'status' => 1, // ACTIVE
    ]);

    echo "✓ BookedDevice 2 created: Device {$devices[1]->name}\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "=== Step 3: Check Activities ===\n\n";

// Get session activity
$sessionActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->where('event', 'created')
    ->first();

echo "Session Activity:\n";
if ($sessionActivity) {
    $props = json_decode($sessionActivity->properties, true);
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// Get booked device activities
$bookedActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('daily_id', 2)
    ->orderBy('id', 'desc')
    ->limit(2)
    ->get();

echo "\nBookedDevice Activities: {$bookedActivities->count()}\n";
foreach ($bookedActivities as $activity) {
    $props = json_decode($activity->properties, true);
    echo "\nActivity {$activity->id}:\n";
    echo "  Device ID: " . ($props['attributes']['device_id'] ?? 'N/A') . "\n";
    echo "  Status: " . ($props['attributes']['status'] ?? 'N/A') . "\n";
}

echo "\n✓ Test completed!\n";
