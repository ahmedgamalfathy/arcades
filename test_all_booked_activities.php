<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== All BookedDevice Activities ===\n\n";

$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->get();

echo "Total: {$activities->count()}\n\n";

foreach ($activities as $act) {
    $props = json_decode($act->properties, true);
    $sessionId = $props['attributes']['session_device_id'] ?? 'N/A';

    echo "Activity ID: {$act->id}\n";
    echo "  Event: {$act->event}\n";
    echo "  Daily ID: {$act->daily_id}\n";
    echo "  Session ID: {$sessionId}\n";
    echo "  Created: {$act->created_at}\n\n";
}
