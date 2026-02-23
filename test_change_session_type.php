<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Change Session Type ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

$dailyId = 2;

// Step 1: Create Individual Session (type = 0)
echo "Step 1: Create Individual Session\n";

$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'type' => 0, // Individual
    'name' => 'Individual Session',
    'daily_id' => $dailyId,
]);

echo "✓ Session created (ID: {$session->id})\n";
echo "  Type: {$session->type} (0 = Individual)\n";
echo "  Name: {$session->name}\n\n";

// Step 2: Change to Group (type = 1)
echo "Step 2: Change Session Type to Group\n";

$session->type = 1; // Group
$session->name = 'Group Session';
$session->save();

echo "✓ Session type changed\n";
echo "  New Type: {$session->type} (1 = Group)\n";
echo "  New Name: {$session->name}\n\n";

// Check activities
echo str_repeat('=', 80) . "\n";
echo "=== Checking Activities ===\n\n";

$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->orderBy('id', 'asc')
    ->get();

echo "Total activities: " . $activities->count() . "\n\n";

foreach ($activities as $act) {
    echo "Activity ID: {$act->id}, Event: {$act->event}\n";
    $props = json_decode($act->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Test API Response
echo str_repeat('=', 80) . "\n";
echo "=== API Response ===\n\n";

$dailyRelatedActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('daily_id', $dailyId)
    ->get();

$dailyOwnActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Daily\\Daily')
    ->where('subject_id', $dailyId)
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

// Find Session activities
$sessionActivities = $groupedActivities->filter(function($act) use ($session) {
    return strtolower($act->log_name) === 'sessiondevice' && $act->subject_id == $session->id;
});

echo "Session Activities:\n\n";

foreach ($sessionActivities as $act) {
    $resource = new App\Http\Resources\ActivityLog\Test\AllDailyActivityResource($act);
    $response = $resource->toArray(request());

    echo "Event: {$act->event}\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "✓ Test completed!\n";
