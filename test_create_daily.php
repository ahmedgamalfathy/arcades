<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Daily Creation ===\n\n";

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
echo "End Time: " . ($daily->end_date_time ?? 'Still Open') . "\n";

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
    echo "Log Name: {$createActivity->log_name}\n";

    $properties = json_decode($createActivity->properties, true);
    echo "\nProperties:\n";
    echo json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "\n⚠ No activity log found for this daily creation\n";
}

echo "\n✓ Test completed!\n";
