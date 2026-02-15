<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Create and Delete SessionDevice with BookedDevices ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

$dailyId = 2;

echo "Step 1: Create Group Session with 2 devices\n";

// Create session
$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'type' => 1, // Group
    'name' => 'Test Group Session',
    'daily_id' => $dailyId,
]);

echo "✓ Session created (ID: {$session->id})\n\n";

// Create 2 booked devices
$device1 = App\Models\Timer\BookedDevice\BookedDevice::create([
    'session_device_id' => $session->id,
    'device_id' => 1,
    'device_type_id' => 1,
    'device_time_id' => 1,
    'status' => 1,
    'start_date_time' => now(),
]);

echo "✓ Device 1 created (ID: {$device1->id})\n";

$device2 = App\Models\Timer\BookedDevice\BookedDevice::create([
    'session_device_id' => $session->id,
    'device_id' => 1, // Same device
    'device_type_id' => 1,
    'device_time_id' => 2,
    'status' => 1,
    'start_date_time' => now(),
]);

echo "✓ Device 2 created (ID: {$device2->id})\n\n";

echo "Step 2: Delete SessionDevice\n";

// Delete the session
$session->delete();

echo "✓ SessionDevice deleted!\n\n";

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

// Find SessionDevice deleted activity
$sessionDeleted = $groupedActivities->first(function($act) {
    return strtolower($act->log_name) === 'sessiondevice' && $act->event === 'deleted';
});

if ($sessionDeleted) {
    $resource = new App\Http\Resources\ActivityLog\Test\AllDailyActivityResource($sessionDeleted);
    $response = $resource->toArray(request());

    echo "SessionDevice Delete Activity with children:\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "SessionDevice deleted activity not found!\n";
}

echo "\n✓ Test completed!\n";
