<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking Activities Timing ===\n\n";

// Get Session 4 activity
$sessionActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', 4)
    ->first();

echo "Session Activity:\n";
echo "  ID: {$sessionActivity->id}\n";
echo "  Created: {$sessionActivity->created_at}\n\n";

// Get BookedDevice activity
$bookedActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('daily_id', 2)
    ->orderBy('id', 'desc')
    ->first();

if ($bookedActivity) {
    echo "BookedDevice Activity:\n";
    echo "  ID: {$bookedActivity->id}\n";
    echo "  Created: {$bookedActivity->created_at}\n";
    echo "  Session ID from properties: ";

    $props = json_decode($bookedActivity->properties, true);
    echo ($props['attributes']['session_device_id'] ?? 'N/A') . "\n\n";

    // Calculate time difference
    $sessionTime = \Carbon\Carbon::parse($sessionActivity->created_at);
    $bookedTime = \Carbon\Carbon::parse($bookedActivity->created_at);
    $diff = $sessionTime->diffInSeconds($bookedTime);

    echo "Time difference: {$diff} seconds\n";
    echo "Within 10 seconds window: " . ($diff <= 10 ? 'YES' : 'NO') . "\n";
} else {
    echo "No BookedDevice activity found!\n";
}
