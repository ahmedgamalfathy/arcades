<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Daily Create and Delete ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Step 1: Create Daily
echo "Step 1: Create Daily\n";

$daily = App\Models\Daily\Daily::create([
    'start_date_time' => '2026-02-13 08:00:00',
    'end_date_time' => '2026-02-13 20:00:00',
]);

echo "✓ Daily created (ID: {$daily->id})\n";
echo "  Start: {$daily->start_date_time}\n";
echo "  End: {$daily->end_date_time}\n\n";

// Step 2: Delete Daily
echo "Step 2: Delete Daily\n";

$daily->delete();

echo "✓ Daily deleted\n\n";

// Test API Response
echo str_repeat('=', 80) . "\n";
echo "=== API Response ===\n\n";

$dailyRelatedActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('daily_id', $daily->id)
    ->get();

$dailyOwnActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Daily\\Daily')
    ->where('subject_id', $daily->id)
    ->get();

$activities = $dailyRelatedActivities->merge($dailyOwnActivities)
    ->sortByDesc('created_at')
    ->values();

$userIds = $activities->pluck('causer_id')->unique()->filter();
$users = DB::connection('mysql')->table('users')
    ->whereIn('id', $userIds)
    ->pluck('name', 'id');

$allActivities = $activities->map(function ($activity) use ($users) {
    $activity->properties = json_decode($activity->properties, true);
    $activity->causerName = $users[$activity->causer_id] ?? null;
    return $activity;
});

$controller = new App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('groupParentChildActivities');
$method->setAccessible(true);

$groupedActivities = $method->invoke($controller, $allActivities);

echo "Daily Activities:\n\n";

foreach ($groupedActivities as $act) {
    $resource = new App\Http\Resources\ActivityLog\Test\AllDailyActivityResource($act);
    $response = $resource->toArray(request());

    echo "Event: {$act->event}\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "✓ Test completed!\n";
