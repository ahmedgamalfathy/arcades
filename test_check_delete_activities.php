<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking Delete Activities for Session 15 ===\n\n";

// Get all activities for session 15
$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('daily_id', 2)
    ->where(function($q) {
        $q->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
          ->where('subject_id', 15);
    })
    ->orWhere(function($q) {
        $q->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
          ->whereIn('subject_id', [14, 15]);
    })
    ->orderBy('id', 'desc')
    ->get();

echo "Total activities: " . $activities->count() . "\n\n";

foreach ($activities as $act) {
    echo "ID: {$act->id}, Model: {$act->log_name}, Subject ID: {$act->subject_id}, Event: {$act->event}, Time: {$act->created_at}\n";

    $props = json_decode($act->properties, true);
    if ($act->event === 'deleted') {
        echo "  Old data: " . json_encode($props['old'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}
