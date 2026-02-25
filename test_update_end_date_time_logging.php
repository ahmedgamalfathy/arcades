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

echo "=== Testing updateEndDateTime Activity Logging ===\n\n";

// Get a booked device
$bookedDevice = BookedDevice::where('status', '!=', 0)->latest()->first();

if (!$bookedDevice) {
    echo "No active booked device found.\n";
    exit;
}

echo "BookedDevice Details:\n";
echo "- ID: {$bookedDevice->id}\n";
echo "- Device Name: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "- Device Type: " . ($bookedDevice->deviceType?->name ?? 'N/A') . "\n";
echo "- Current end_date_time: " . ($bookedDevice->end_date_time ?? 'NULL') . "\n\n";

// Count activities before
$activitiesCountBefore = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->count();

echo "Activities BEFORE: {$activitiesCountBefore}\n\n";

// Update end_date_time using service
$service = app(BookedDeviceService::class);
$newEndTime = Carbon::now()->addHours(5);

echo "Updating end_date_time to: {$newEndTime}\n";
$service->updateEndDateTime($bookedDevice->id, ['endDateTime' => $newEndTime->format('Y-m-d H:i:s')]);

// Count activities after
$activitiesCountAfter = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->count();

echo "Activities AFTER: {$activitiesCountAfter}\n\n";

if ($activitiesCountAfter > $activitiesCountBefore) {
    echo "✓ Activity log created!\n\n";

    $activity = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
        ->where('subject_id', $bookedDevice->id)
        ->orderBy('id', 'desc')
        ->first();

    echo "Raw Properties:\n";
    echo json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n\n";

    $resource = new AllDailyActivityResource($activity);
    $result = $resource->toArray(request());

    echo "Resource Output:\n";
    echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "Verification:\n";
    $checks = [
        'deviceName' => 'Device Name',
        'deviceType' => 'Device Type',
        'endTime' => 'End Time'
    ];

    foreach ($checks as $key => $label) {
        if (isset($result['details'][$key])) {
            echo "✓ {$label} present\n";
            if ($key === 'endTime') {
                echo "  Old: {$result['details'][$key]['old']}\n";
                echo "  New: {$result['details'][$key]['new']}\n";
            } else {
                echo "  Value: {$result['details'][$key]['new']}\n";
            }
        } else {
            echo "✗ {$label} NOT present\n";
        }
    }
} else {
    echo "✗ No activity log created\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Test Complete\n";
echo str_repeat('=', 70) . "\n";
