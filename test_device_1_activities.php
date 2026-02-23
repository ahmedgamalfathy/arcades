<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking BookedDevice ID = 1 ===\n\n";

// Check if device exists
$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::withTrashed()->find(1);

if (!$bookedDevice) {
    echo "BookedDevice ID = 1 not found!\n";
    exit;
}

echo "BookedDevice ID: {$bookedDevice->id}\n";
echo "Device: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "Session ID: {$bookedDevice->session_device_id}\n";
echo "Deleted: " . ($bookedDevice->deleted_at ? 'Yes' : 'No') . "\n\n";

// Check activities
echo "Checking activities in database:\n\n";

$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', 1)
    ->get();

echo "BookedDevice activities: " . $activities->count() . "\n";
foreach ($activities as $act) {
    echo "  - Activity ID: {$act->id}, Event: {$act->event}, Time: {$act->created_at}\n";
}
echo "\n";

// Check session activities
if ($bookedDevice->session_device_id) {
    $sessionActivities = DB::connection('tenant')
        ->table('activity_log')
        ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
        ->where('subject_id', $bookedDevice->session_device_id)
        ->get();

    echo "SessionDevice activities: " . $sessionActivities->count() . "\n";
    foreach ($sessionActivities as $act) {
        echo "  - Activity ID: {$act->id}, Event: {$act->event}, Time: {$act->created_at}\n";
    }
    echo "\n";
}

// Check orders
$orders = $bookedDevice->orders;
echo "Orders: " . $orders->count() . "\n";
foreach ($orders as $order) {
    echo "  - Order ID: {$order->id}\n";
}
echo "\n";

// Check pauses
$pauses = $bookedDevice->pauses;
echo "Pauses: " . $pauses->count() . "\n";
foreach ($pauses as $pause) {
    echo "  - Pause ID: {$pause->id}\n";
}
echo "\n";

// Test the service
echo str_repeat('=', 80) . "\n";
echo "=== Testing Service ===\n\n";

try {
    $service = new App\Services\Timer\BookedDeviceService();
    $activities = $service->getActivityLogToDevice(1);

    echo "Activities returned: " . $activities->count() . "\n";
    foreach ($activities as $act) {
        echo "  - {$act->log_name} (ID: {$act->id}, Event: {$act->event})\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✓ Test completed!\n";
