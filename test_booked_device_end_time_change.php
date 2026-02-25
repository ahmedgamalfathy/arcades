<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Switch to tenant database
config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;
use Carbon\Carbon;

echo "=== Testing BookedDevice end_date_time Change in Activity Log ===\n\n";

// Get a booked device
$bookedDevice = BookedDevice::latest()->first();

if (!$bookedDevice) {
    echo "No booked device found.\n";
    exit;
}

echo "BookedDevice Details:\n";
echo "- ID: {$bookedDevice->id}\n";
echo "- Device Name: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "- Current end_date_time: " . ($bookedDevice->end_date_time ?? 'NULL') . "\n\n";

// Change end_date_time
$oldEndTime = $bookedDevice->end_date_time;
$newEndTime = Carbon::now()->addHours(2);

echo "Changing end_date_time:\n";
echo "- Old: " . ($oldEndTime ?? 'NULL') . "\n";
echo "- New: {$newEndTime}\n\n";

$bookedDevice->end_date_time = $newEndTime;
$bookedDevice->save();

echo "BookedDevice Updated\n\n";

// Get the activity log
$activity = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->where('event', 'updated')
    ->latest()
    ->first();

if (!$activity) {
    echo "No activity log found.\n";
    exit;
}

echo "Activity Log Details:\n";
echo str_repeat('-', 70) . "\n";

$resource = new AllDailyActivityResource($activity);
$result = $resource->toArray(request());

echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Check if endTime is present
if (isset($result['details']['endTime'])) {
    echo "✓ SUCCESS: 'endTime' field found in activity log\n";
    echo "  Old: {$result['details']['endTime']['old']}\n";
    echo "  New: {$result['details']['endTime']['new']}\n";
} else {
    echo "✗ FAILED: 'endTime' field NOT found in activity log\n";
    echo "\nAvailable fields:\n";
    foreach (array_keys($result['details']) as $key) {
        echo "  - {$key}\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Test Complete\n";
echo str_repeat('=', 70) . "\n";
