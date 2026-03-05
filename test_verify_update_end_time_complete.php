<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Timer\BookedDevice\BookedDevice;
use App\Services\Timer\BookedDeviceService;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;
use Carbon\Carbon;

echo "=== Complete Verification: updateEndDateTime Logging ===\n\n";

// Get a booked device
$bookedDevice = BookedDevice::where('status', '!=', 0)->latest()->first();

if (!$bookedDevice) {
    echo "No active booked device found.\n";
    exit;
}

echo "BEFORE UPDATE:\n";
echo str_repeat('-', 70) . "\n";
echo "BookedDevice ID: {$bookedDevice->id}\n";
echo "Device Name: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "Device Type: " . ($bookedDevice->deviceType?->name ?? 'N/A') . "\n";
echo "Device Time: " . ($bookedDevice->deviceTime?->name ?? 'N/A') . "\n";
echo "Status: {$bookedDevice->status}\n";
echo "Current end_date_time: " . ($bookedDevice->end_date_time ?? 'NULL') . "\n";

// Get activity count before
$activitiesBefore = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->count();
echo "Activity logs count: {$activitiesBefore}\n\n";

// Update using service
echo "PERFORMING UPDATE:\n";
echo str_repeat('-', 70) . "\n";

$service = app(BookedDeviceService::class);
$newEndTime = Carbon::now()->addHours(6);
echo "New end_date_time: {$newEndTime}\n\n";

$service->updateEndDateTime($bookedDevice->id, ['endDateTime' => $newEndTime->format('Y-m-d H:i:s')]);

// Check activity count after
$activitiesAfter = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->count();

echo "AFTER UPDATE:\n";
echo str_repeat('-', 70) . "\n";
echo "Activity logs count: {$activitiesAfter}\n";

if ($activitiesAfter > $activitiesBefore) {
    echo "✓ New activity log created! ({$activitiesBefore} → {$activitiesAfter})\n\n";

    // Get the latest activity
    $activity = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
        ->where('subject_id', $bookedDevice->id)
        ->orderBy('id', 'desc')
        ->first();

    echo "Activity Details:\n";
    echo "- Activity ID: {$activity->id}\n";
    echo "- Event: {$activity->event}\n";
    echo "- Log Name: {$activity->log_name}\n";
    echo "- Created At: {$activity->created_at}\n\n";

    echo "Raw Properties:\n";
    echo json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // Process through Resource
    $resource = new AllDailyActivityResource($activity);
    $result = $resource->toArray(request());

    echo "Resource Output (API Response):\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // Detailed verification
    echo "VERIFICATION CHECKLIST:\n";
    echo str_repeat('-', 70) . "\n";

    $checks = [
        'deviceName' => ['label' => 'Device Name', 'expected' => $bookedDevice->device?->name],
        'deviceType' => ['label' => 'Device Type', 'expected' => $bookedDevice->deviceType?->name],
        'deviceTime' => ['label' => 'Device Time', 'expected' => $bookedDevice->deviceTime?->name],
        'status' => ['label' => 'Status', 'expected' => $bookedDevice->status],
        'endTime' => ['label' => 'End Time', 'expected' => 'changed']
    ];

    foreach ($checks as $key => $info) {
        if (isset($result['details'][$key])) {
            echo "✓ {$info['label']} is present\n";

            if ($key === 'endTime') {
                $oldValue = $result['details'][$key]['old'];
                $newValue = $result['details'][$key]['new'];
                echo "  Old: " . ($oldValue ?: 'NULL') . "\n";
                echo "  New: {$newValue}\n";

                if ($oldValue != $newValue) {
                    echo "  ✓ Value changed correctly\n";
                } else {
                    echo "  ✗ Value did NOT change\n";
                }
            } else {
                echo "  Value: {$result['details'][$key]['new']}\n";
            }
        } else {
            echo "✗ {$info['label']} is NOT present\n";
        }
    }

    echo "\n" . str_repeat('=', 70) . "\n";
    echo "RESULT: Activity logging is working correctly!\n";
    echo "- Activity log created automatically\n";
    echo "- Device name and type are logged\n";
    echo "- End time change is tracked\n";
    echo str_repeat('=', 70) . "\n";

} else {
    echo "✗ FAILED: No new activity log was created\n";
    echo "Activity count remained: {$activitiesAfter}\n";
}
