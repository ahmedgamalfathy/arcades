<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Daily Creation with Authentication ===\n\n";

// Authenticate as user 1
$user = DB::connection('mysql')->table('users')->where('id', 1)->first();
if (!$user) {
    echo "User not found!\n";
    exit;
}

echo "Authenticating as: {$user->name} (ID: {$user->id})\n\n";

// Set the authenticated user for activity log
auth()->setUser(App\Models\User::find(1));

// Check if there's an open daily
$openDaily = App\Models\Daily\Daily::where('end_date_time', null)->first();

if ($openDaily) {
    echo "⚠ There is already an open daily (ID: {$openDaily->id})\n";
    echo "Closing it first...\n\n";

    $openDaily->end_date_time = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
    $openDaily->save();
    echo "✓ Daily {$openDaily->id} closed\n\n";
}

// Create new daily
$dailyService = app(App\Services\Daily\DailyService::class);

$dailyData = [
    'startDateTime' => \Carbon\Carbon::now()->format('Y-m-d H:i:s'),
    'endDateTime' => null,
    'totalIncome' => null,
    'totalExpense' => null,
    'totalProfit' => null,
];

echo "Creating new daily...\n";
$daily = $dailyService->createDaily($dailyData);
echo "✓ Daily created!\n";
echo "Daily ID: {$daily->id}\n";
echo "Start Time: {$daily->start_date_time}\n";

// Get create activity
$createActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Daily\\Daily')
    ->where('subject_id', $daily->id)
    ->where('event', 'created')
    ->orderBy('id', 'desc')
    ->first();

if ($createActivity) {
    echo "\n=== Create Activity Log ===\n";
    echo "Activity ID: {$createActivity->id}\n";
    echo "Event: {$createActivity->event}\n";
    echo "Causer ID: " . ($createActivity->causer_id ?? 'NULL') . "\n";
    echo "Causer Type: " . ($createActivity->causer_type ?? 'NULL') . "\n";

    if ($createActivity->causer_id) {
        $causer = DB::connection('mysql')->table('users')->where('id', $createActivity->causer_id)->first();
        echo "Causer Name: " . ($causer->name ?? 'Unknown') . "\n";
    }
}

echo "\n✓ Test completed!\n";
