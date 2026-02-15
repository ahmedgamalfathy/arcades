<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking Activity 63 Properties ===\n\n";

$act = DB::connection('tenant')
    ->table('activity_log')
    ->where('id', 63)
    ->first();

if ($act) {
    echo "Activity ID: {$act->id}\n";
    echo "Event: {$act->event}\n";
    echo "Subject: {$act->subject_type} (ID: {$act->subject_id})\n\n";

    $props = json_decode($act->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    echo "session_device_id in attributes: " . ($props['attributes']['session_device_id'] ?? 'NOT FOUND') . "\n";
    echo "session_device_id in old: " . ($props['old']['session_device_id'] ?? 'NOT FOUND') . "\n";
} else {
    echo "Activity 63 not found!\n";
}
