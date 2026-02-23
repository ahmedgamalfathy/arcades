<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Delete SessionDevice with BookedDevices ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Get the last session in daily 2
$session = App\Models\Timer\SessionDevice\SessionDevice::where('daily_id', 2)
    ->orderBy('id', 'desc')
    ->first();

if (!$session) {
    echo "No session found in daily 2!\n";
    exit;
}

echo "Session ID: {$session->id}\n";
echo "Session Name: {$session->name}\n";
echo "Daily ID: {$session->daily_id}\n\n";

// Get booked devices for this session
$bookedDevices = App\Models\Timer\BookedDevice\BookedDevice::where('session_device_id', $session->id)->get();

echo "BookedDevices in this session: " . $bookedDevices->count() . "\n";
foreach ($bookedDevices as $device) {
    echo "  - ID: {$device->id}, Device: " . ($device->device?->name ?? 'N/A') . "\n";
}
echo "\n";

echo "Step 1: Delete SessionDevice (should also delete BookedDevices)\n";

// Delete the session (should cascade delete booked devices)
$session->delete();

echo "✓ SessionDevice deleted!\n\n";

// Check delete activities
$sessionDeleteActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->where('event', 'deleted')
    ->orderBy('id', 'desc')
    ->first();

if ($sessionDeleteActivity) {
    echo "SessionDevice Delete Activity found (ID: {$sessionDeleteActivity->id})\n";
    $props = json_decode($sessionDeleteActivity->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Check BookedDevice delete activities
$bookedDeleteActivities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('event', 'deleted')
    ->whereIn('subject_id', $bookedDevices->pluck('id'))
    ->get();

echo "BookedDevice Delete Activities: " . $bookedDeleteActivities->count() . "\n";
foreach ($bookedDeleteActivities as $act) {
    echo "  - Activity ID: {$act->id}, Subject ID: {$act->subject_id}\n";
}
echo "\n";

// Test API Response
echo str_repeat('=', 80) . "\n";
echo "=== API Response ===\n\n";

$dailyId = 2;

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
    ->values()
    ->take(1); // Get only the latest activity

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

echo "Latest Activity (SessionDevice Delete):\n";
echo json_encode($response[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n✓ Test completed!\n";
