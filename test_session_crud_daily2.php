<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing SessionDevice CRUD in Daily 2 ===\n\n";

// Authenticate as user 1
auth()->setUser(App\Models\User::find(1));
$user = auth()->user();
echo "Authenticated as: {$user->name}\n\n";

// Check Daily 2
$daily = App\Models\Daily\Daily::find(2);
if (!$daily) {
    echo "Daily 2 not found!\n";
    exit;
}
echo "Daily 2 found: {$daily->start_date_time}\n\n";

// ==================== Step 1: Create Session ====================
echo str_repeat('=', 80) . "\n";
echo "=== Step 1: Create Session ===\n\n";

$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'name' => 'احمد محسين',
    'daily_id' => 2,
    'type' => 1,
]);

echo "✓ Session created!\n";
echo "Session ID: {$session->id}\n";
echo "Session Name: {$session->name}\n";
echo "Daily ID: {$session->daily_id}\n";

// Get create activity
$createActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->where('event', 'created')
    ->orderBy('id', 'desc')
    ->first();

if ($createActivity) {
    echo "\nCreate Activity ID: {$createActivity->id}\n";
    $properties = json_decode($createActivity->properties, true);
    echo "Properties:\n";
    echo json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

sleep(1);

// ==================== Step 2: Update Session ====================
echo "\n" . str_repeat('=', 80) . "\n";
echo "=== Step 2: Update Session ===\n\n";

$session->name = 'محمد علي';
$session->type = 2;
$session->save();

echo "✓ Session updated!\n";
echo "New Name: {$session->name}\n";
echo "New Type: {$session->type}\n";

// Get update activity
$updateActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->where('event', 'updated')
    ->orderBy('id', 'desc')
    ->first();

if ($updateActivity) {
    echo "\nUpdate Activity ID: {$updateActivity->id}\n";
    $properties = json_decode($updateActivity->properties, true);
    echo "Properties:\n";
    echo json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

sleep(1);

// ==================== Step 3: Delete Session ====================
echo "\n" . str_repeat('=', 80) . "\n";
echo "=== Step 3: Delete Session ===\n\n";

$sessionId = $session->id;
$session->delete();

echo "✓ Session deleted!\n";

// Get delete activity
$deleteActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $sessionId)
    ->where('event', 'deleted')
    ->orderBy('id', 'desc')
    ->first();

if ($deleteActivity) {
    echo "\nDelete Activity ID: {$deleteActivity->id}\n";
    $properties = json_decode($deleteActivity->properties, true);
    echo "Properties:\n";
    echo json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// ==================== Step 4: Test API Response ====================
echo "\n" . str_repeat('=', 80) . "\n";
echo "=== Step 4: Test API Response for Daily 2 ===\n\n";

// Get all activities for daily 2
$dailyRelatedActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('daily_id', 2)
    ->get();

$dailyOwnActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Daily\\Daily')
    ->where('subject_id', 2)
    ->get();

$activities = $dailyRelatedActivities->merge($dailyOwnActivities)
    ->sortByDesc('created_at')
    ->values()
    ->take(5); // Get last 5 activities

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
$resource = App\Http\Resources\ActivityLog\Test\AllDailyActivityResource::collection($groupedActivities);
$response = $resource->toArray(request());

echo "Last 5 Activities in Daily 2:\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n" . str_repeat('=', 80) . "\n";
echo "✓ Test completed successfully!\n";
