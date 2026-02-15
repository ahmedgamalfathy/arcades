<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Device Activity Log ===\n\n";

// Get a booked device
$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::orderBy('id', 'desc')->first();

if (!$bookedDevice) {
    echo "No booked device found!\n";
    exit;
}

echo "BookedDevice ID: {$bookedDevice->id}\n";
echo "Device: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "Session: " . ($bookedDevice->sessionDevice?->name ?? 'N/A') . " (ID: {$bookedDevice->session_device_id})\n\n";

// Get activity log using the service
$service = new App\Services\Timer\BookedDeviceService();
$activityLog = $service->getActivityLogToDevice($bookedDevice->id);

echo "Activity Log Summary:\n";
echo "  BookedDevice: " . $activityLog['booked_device']->count() . "\n";
echo "  Orders: " . $activityLog['orders']->count() . "\n";
echo "  Order Items: " . $activityLog['order_items']->count() . "\n";
echo "  Sessions: " . $activityLog['sessions']->count() . "\n";
echo "  Pauses: " . $activityLog['pauses']->count() . "\n\n";

// Show details
if ($activityLog['booked_device']->count() > 0) {
    echo "BookedDevice:\n";
    foreach ($activityLog['booked_device'] as $activity) {
        echo "  - Activity ID: {$activity->id}, Event: {$activity->event}, Subject ID: {$activity->subject_id}\n";
    }
    echo "\n";
}

if ($activityLog['orders']->count() > 0) {
    echo "Orders:\n";
    foreach ($activityLog['orders'] as $activity) {
        echo "  - Activity ID: {$activity->id}, Event: {$activity->event}, Subject ID: {$activity->subject_id}\n";
    }
    echo "\n";
}

if ($activityLog['order_items']->count() > 0) {
    echo "Order Items:\n";
    foreach ($activityLog['order_items'] as $activity) {
        echo "  - Activity ID: {$activity->id}, Event: {$activity->event}, Subject ID: {$activity->subject_id}\n";
    }
    echo "\n";
}

if ($activityLog['sessions']->count() > 0) {
    echo "Sessions:\n";
    foreach ($activityLog['sessions'] as $activity) {
        echo "  - Activity ID: {$activity->id}, Event: {$activity->event}, Subject ID: {$activity->subject_id}\n";
    }
    echo "\n";
}

if ($activityLog['pauses']->count() > 0) {
    echo "Pauses:\n";
    foreach ($activityLog['pauses'] as $activity) {
        echo "  - Activity ID: {$activity->id}, Event: {$activity->event}, Subject ID: {$activity->subject_id}\n";
    }
    echo "\n";
}

// Check if BookedDevice activities are included
echo str_repeat('=', 80) . "\n";
echo "=== Checking BookedDevice Activities ===\n\n";

$bookedDeviceActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->get();

echo "BookedDevice activities (NOT included in current response): " . $bookedDeviceActivities->count() . "\n";
foreach ($bookedDeviceActivities as $activity) {
    echo "  - Activity ID: {$activity->id}, Event: {$activity->event}\n";
}

echo "\n✓ Test completed!\n";
