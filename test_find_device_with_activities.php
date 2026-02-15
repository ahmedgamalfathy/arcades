<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Finding BookedDevices with Activities ===\n\n";

// Get all booked device IDs that have activities
$deviceIdsWithActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->distinct()
    ->pluck('subject_id');

echo "BookedDevices with activities: " . $deviceIdsWithActivities->count() . "\n";
foreach ($deviceIdsWithActivities as $id) {
    $device = App\Models\Timer\BookedDevice\BookedDevice::withTrashed()->find($id);
    if ($device) {
        echo "  - ID: {$id}, Device: " . ($device->device?->name ?? 'N/A') . ", Session: {$device->session_device_id}\n";
    }
}

echo "\n";

// Pick the first one and test
if ($deviceIdsWithActivities->count() > 0) {
    $testId = $deviceIdsWithActivities->first();
    echo "Testing with BookedDevice ID: {$testId}\n\n";

    $service = new App\Services\Timer\BookedDeviceService();
    $activities = $service->getActivityLogToDevice($testId);

    echo "Activities returned: " . $activities->count() . "\n";
    foreach ($activities as $act) {
        echo "  - {$act->log_name} (ID: {$act->id}, Event: {$act->event})\n";
    }

    echo "\n\nYou can test the API with: /api/v1/admin/device-timer/{$testId}/activity-log\n";
} else {
    echo "No BookedDevices with activities found!\n";
}

echo "\n✓ Test completed!\n";
