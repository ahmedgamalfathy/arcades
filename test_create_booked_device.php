<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Creating BookedDevice for Session 4 ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

$session = App\Models\Timer\SessionDevice\SessionDevice::find(4);
if (!$session) {
    echo "Session 4 not found!\n";
    exit;
}

echo "Session: {$session->name}\n\n";

// Get data
$device = App\Models\Device\Device::first();
$deviceType = App\Models\Device\DeviceType\DeviceType::first();
$deviceTime = App\Models\Device\DeviceTime\DeviceTime::first();

if (!$device || !$deviceType || !$deviceTime) {
    echo "Missing required data!\n";
    exit;
}

echo "Device: {$device->name} (ID: {$device->id})\n";
echo "Device Type: {$deviceType->name} (ID: {$deviceType->id})\n";
echo "Device Time: {$deviceTime->name} (ID: {$deviceTime->id})\n\n";

// Create BookedDevice
$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::create([
    'session_device_id' => $session->id,
    'device_id' => $device->id,
    'device_type_id' => $deviceType->id,
    'device_time_id' => $deviceTime->id,
    'start_date_time' => \Carbon\Carbon::now(),
    'status' => 1, // ACTIVE
]);

echo "✓ BookedDevice created (ID: {$bookedDevice->id})\n\n";

// Check activity
$activity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->where('event', 'created')
    ->first();

if ($activity) {
    echo "Activity found (ID: {$activity->id})\n";
    echo "Daily ID: " . ($activity->daily_id ?? 'NULL') . "\n\n";

    $props = json_decode($activity->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "No activity found!\n";
}
