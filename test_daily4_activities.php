<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Daily 4 Activities ===\n\n";

$dailyId = 4;

// Get activities related to this daily
// 1. Activities with daily_id = $dailyId
$dailyRelatedActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('daily_id', $dailyId)
    ->get();

echo "Activities with daily_id = {$dailyId}: {$dailyRelatedActivities->count()}\n";

// 2. Activities for the Daily itself
$dailyOwnActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Daily\\Daily')
    ->where('subject_id', $dailyId)
    ->get();

echo "Activities for Daily itself (subject_type = Daily, subject_id = {$dailyId}): {$dailyOwnActivities->count()}\n\n";

// Merge both
$activities = $dailyRelatedActivities->merge($dailyOwnActivities)
    ->sortByDesc('created_at')
    ->values();

echo "Total activities: {$activities->count()}\n\n";

// Get user names
$userIds = $activities->pluck('causer_id')->unique()->filter();
$users = DB::connection('mysql')->table('users')
    ->whereIn('id', $userIds)
    ->pluck('name', 'id');

$allActivities = $activities->map(function ($activity) use ($users) {
    $activity->properties = json_decode($activity->properties, true);
    $activity->causerName = $users[$activity->causer_id] ?? null;
    return $activity;
});

// Use controller to group activities
$controller = new App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('groupParentChildActivities');
$method->setAccessible(true);

$groupedActivities = $method->invoke($controller, $allActivities);
$resource = App\Http\Resources\ActivityLog\Test\AllDailyActivityResource::collection($groupedActivities);
$response = $resource->toArray(request());

echo "API Response:\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
