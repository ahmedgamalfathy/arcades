<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking Causer ID for Daily 4 Activity ===\n\n";

// Get the activity
$activity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Daily\\Daily')
    ->where('subject_id', 4)
    ->first();

if ($activity) {
    echo "Activity ID: {$activity->id}\n";
    echo "Causer ID: " . ($activity->causer_id ?? 'NULL') . "\n";
    echo "Causer Type: " . ($activity->causer_type ?? 'NULL') . "\n\n";

    if ($activity->causer_id) {
        // Get user from main database
        $user = DB::connection('mysql')
            ->table('users')
            ->where('id', $activity->causer_id)
            ->first();

        if ($user) {
            echo "User found in main database:\n";
            echo "  ID: {$user->id}\n";
            echo "  Name: {$user->name}\n";
            echo "  Email: {$user->email}\n";
        } else {
            echo "⚠ User not found in main database!\n";
        }
    } else {
        echo "⚠ No causer_id in activity log\n";
        echo "\nThis means the activity was created without authentication.\n";
        echo "Check if you're authenticated when creating the daily.\n";
    }
} else {
    echo "Activity not found!\n";
}

// List all users
echo "\n\n=== All Users in Main Database ===\n";
$users = DB::connection('mysql')->table('users')->get();
foreach ($users as $user) {
    echo "User {$user->id}: {$user->name} ({$user->email})\n";
}
