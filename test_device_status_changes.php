<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing BookedDevice Status Changes ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

$dailyId = 2;

// Step 1: Create Session and Device (status = 1 ACTIVE)
echo "Step 1: Create Individual Session with Device (ACTIVE)\n";

$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'type' => 0,
    'name' => 'Test Status Session',
    'daily_id' => $dailyId,
]);

$device = App\Models\Timer\BookedDevice\BookedDevice::create([
    'session_device_id' => $session->id,
    'device_id' => 1,
    'device_type_id' => 1,
    'device_time_id' => 1,
    'status' => 1, // ACTIVE
    'start_date_time' => now(),
]);

echo "✓ Device created (ID: {$device->id})\n";
echo "  Status: {$device->status} (1 = ACTIVE)\n\n";

// Step 2: Change to PAUSED
echo "Step 2: Change Status to PAUSED\n";

$device->status = 2; // PAUSED
$device->save();

echo "✓ Status changed to PAUSED\n";
echo "  Status: {$device->status} (2 = PAUSED)\n\n";

// Step 3: Change to RESUME
echo "Step 3: Change Status to RESUME\n";

$device->status = 3; // RESUME
$device->save();

echo "✓ Status changed to RESUME\n";
echo "  Status: {$device->status} (3 = RESUME)\n\n";

// Step 4: Change to FINISHED
echo "Step 4: Change Status to FINISHED\n";

$device->status = 0; // FINISHED
$device->save();

echo "✓ Status changed to FINISHED\n";
echo "  Status: {$device->status} (0 = FINISHED)\n\n";

// Check activities
echo str_repeat('=', 80) . "\n";
echo "=== Checking Activities ===\n\n";

$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', $device->id)
    ->orderBy('id', 'asc')
    ->get();

echo "Total activities: " . $activities->count() . "\n\n";

foreach ($activities as $act) {
    echo "Activity ID: {$act->id}, Event: {$act->event}\n";
    $props = json_decode($act->properties, true);

    if ($act->event === 'created') {
        echo "  Status: " . ($props['attributes']['status'] ?? 'N/A') . "\n";
    } elseif ($act->event === 'updated') {
        echo "  Old Status: " . ($props['old']['status'] ?? 'N/A') . "\n";
        echo "  New Status: " . ($props['attributes']['status'] ?? 'N/A') . "\n";
    }
    echo "\n";
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

// Find Session with device activities
$sessionActivity = $groupedActivities->first(function($act) use ($session) {
    return strtolower($act->log_name) === 'sessiondevice' && $act->subject_id == $session->id;
});

if ($sessionActivity) {
    $resource = new App\Http\Resources\ActivityLog\Test\AllDailyActivityResource($sessionActivity);
    $response = $resource->toArray(request());

    echo "SessionDevice with BookedDevice children:\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "✓ Test completed!\n";
