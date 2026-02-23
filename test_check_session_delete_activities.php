<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking Session 8 Activities ===\n\n";

// Get Session 8 activities
$sessionActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', 8)
    ->get();

echo "Session Activities:\n";
foreach ($sessionActivities as $act) {
    echo "  ID: {$act->id}, Event: {$act->event}, Time: {$act->created_at}\n";
}

// Get BookedDevice activities for session 8
$bookedActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('daily_id', 2)
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "\nBookedDevice Activities (last 5):\n";
foreach ($bookedActivities as $act) {
    $props = json_decode($act->properties, true);
    $sessionId = $props['attributes']['session_device_id'] ?? ($props['old']['session_device_id'] ?? 'N/A');
    echo "  ID: {$act->id}, Event: {$act->event}, Session: {$sessionId}, Time: {$act->created_at}\n";
}
