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
use Carbon\Carbon;

echo "=== Verifying Activity Log is Created When end_date_time Changes ===\n\n";

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

// Count activities before update
$activitiesCountBefore = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->count();

echo "Activity logs BEFORE update: {$activitiesCountBefore}\n\n";

// Change end_date_time
$oldEndTime = $bookedDevice->end_date_time;
$newEndTime = Carbon::now()->addHours(3);

echo "Updating end_date_time:\n";
echo "- Old: " . ($oldEndTime ?? 'NULL') . "\n";
echo "- New: {$newEndTime}\n\n";

$bookedDevice->end_date_time = $newEndTime;
$bookedDevice->save();

echo "✓ BookedDevice saved\n\n";

// Count activities after update
$activitiesCountAfter = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->count();

echo "Activity logs AFTER update: {$activitiesCountAfter}\n\n";

// Check if new activity was created
if ($activitiesCountAfter > $activitiesCountBefore) {
    echo "✓ SUCCESS: New activity log was created!\n";
    echo "  Activities increased from {$activitiesCountBefore} to {$activitiesCountAfter}\n\n";

    // Get the latest activity
    $latestActivity = Activity::where('subject_type', 'App\Models\Timer\BookedDevice\BookedDevice')
        ->where('subject_id', $bookedDevice->id)
        ->latest()
        ->first();

    echo "Latest Activity Details:\n";
    echo "- Event: {$latestActivity->event}\n";
    echo "- Log Name: {$latestActivity->log_name}\n";
    echo "- Created At: {$latestActivity->created_at}\n\n";

    echo "Properties:\n";
    echo json_encode($latestActivity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} else {
    echo "✗ FAILED: No new activity log was created\n";
    echo "  Activities count remained: {$activitiesCountAfter}\n\n";

    echo "Checking BookedDevice LogsActivity configuration...\n";
    $logOptions = $bookedDevice->getActivitylogOptions();
    echo "- Log Name: " . ($logOptions->logName ?? 'N/A') . "\n";
    echo "- Log Only Dirty: " . ($logOptions->logOnlyDirty ? 'Yes' : 'No') . "\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Verification Complete\n";
echo str_repeat('=', 70) . "\n";
