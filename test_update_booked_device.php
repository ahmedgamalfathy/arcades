<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Update BookedDevice ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Get the last booked device
$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::orderBy('id', 'desc')->first();

if (!$bookedDevice) {
    echo "No booked device found!\n";
    exit;
}

echo "BookedDevice ID: {$bookedDevice->id}\n";
echo "Session ID: {$bookedDevice->session_device_id}\n";
echo "Device: " . ($bookedDevice->device?->name ?? 'N/A') . "\n";
echo "Current Device Time: " . ($bookedDevice->deviceTime?->name ?? 'N/A') . " (ID: {$bookedDevice->device_time_id})\n";
echo "Current Status: {$bookedDevice->status}\n\n";

// Get available device times
$deviceTimes = App\Models\Device\DeviceTime\DeviceTime::all();
echo "Available Device Times:\n";
foreach ($deviceTimes as $time) {
    echo "  {$time->id}: {$time->name} (Rate: {$time->rate})\n";
}
echo "\n";

// Find a different device time
$newDeviceTime = $deviceTimes->where('id', '!=', $bookedDevice->device_time_id)->first();

if (!$newDeviceTime) {
    echo "No other device time available!\n";
    exit;
}

echo "Step 1: Update BookedDevice\n";
echo "  Changing device_time from '{$bookedDevice->deviceTime->name}' to '{$newDeviceTime->name}'\n";
echo "  Changing status from {$bookedDevice->status} to 2\n\n";

// Update the booked device
$bookedDevice->device_time_id = $newDeviceTime->id;
$bookedDevice->status = 2; // Change status
$bookedDevice->save();

echo "✓ BookedDevice updated!\n\n";

// Check update activity
$updateActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->where('event', 'updated')
    ->orderBy('id', 'desc')
    ->first();

if ($updateActivity) {
    echo "Update Activity found (ID: {$updateActivity->id})\n";
    echo "Daily ID: {$updateActivity->daily_id}\n\n";

    $props = json_decode($updateActivity->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Test API Response
echo str_repeat('=', 80) . "\n";
echo "=== API Response ===\n\n";

$dailyId = $bookedDevice->sessionDevice->daily_id;

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
    ->take(1);

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

echo "Latest Activity:\n";
echo json_encode($response[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n✓ Test completed!\n";
