<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Complete Session + Device Flow ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Get required data
$device = App\Models\Device\Device::first();
$deviceType = App\Models\Device\DeviceType\DeviceType::first();
$deviceTime = App\Models\Device\DeviceTime\DeviceTime::first();

// Step 1: Create Session + Device
echo "Step 1: Create Session with Device\n";
$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'name' => 'جلسة كاملة',
    'daily_id' => 2,
    'type' => 1,
]);

$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::create([
    'session_device_id' => $session->id,
    'device_id' => $device->id,
    'device_type_id' => $deviceType->id,
    'device_time_id' => $deviceTime->id,
    'start_date_time' => \Carbon\Carbon::now(),
    'status' => 1,
]);

echo "✓ Session created (ID: {$session->id})\n";
echo "✓ Device created (ID: {$bookedDevice->id})\n\n";

sleep(1);

// Step 2: Delete Device
echo "Step 2: Delete Device\n";
$bookedDevice->delete();
echo "✓ Device deleted\n\n";

// Step 3: Check API
echo "Step 3: API Response\n\n";

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
    ->take(3);

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

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
