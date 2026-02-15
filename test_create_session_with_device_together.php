<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Creating Session with BookedDevice Together ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Get required data
$device = App\Models\Device\Device::first();
$deviceType = App\Models\Device\DeviceType\DeviceType::first();
$deviceTime = App\Models\Device\DeviceTime\DeviceTime::first();

if (!$device || !$deviceType || !$deviceTime) {
    echo "Missing required data!\n";
    exit;
}

echo "Step 1: Create Session\n";
$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'name' => 'جلسة تجريبية',
    'daily_id' => 2,
    'type' => 1,
]);
echo "✓ Session created (ID: {$session->id})\n\n";

echo "Step 2: Create BookedDevice (within 10 seconds)\n";
$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::create([
    'session_device_id' => $session->id,
    'device_id' => $device->id,
    'device_type_id' => $deviceType->id,
    'device_time_id' => $deviceTime->id,
    'start_date_time' => \Carbon\Carbon::now(),
    'status' => 1,
]);
echo "✓ BookedDevice created (ID: {$bookedDevice->id})\n\n";

// Check activities
echo "Step 3: Check Activities\n\n";

$sessionActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->first();

$bookedActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->first();

if ($sessionActivity && $bookedActivity) {
    $sessionTime = \Carbon\Carbon::parse($sessionActivity->created_at);
    $bookedTime = \Carbon\Carbon::parse($bookedActivity->created_at);
    $diff = abs($sessionTime->diffInSeconds($bookedTime));

    echo "Session Activity: {$sessionActivity->id} at {$sessionActivity->created_at}\n";
    echo "BookedDevice Activity: {$bookedActivity->id} at {$bookedActivity->created_at}\n";
    echo "Time difference: {$diff} seconds\n";
    echo "Within 10 seconds: " . ($diff <= 10 ? 'YES ✓' : 'NO ✗') . "\n\n";
}

// Test API
echo "Step 4: Test API Response\n\n";

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
    ->take(2);

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

echo "API Response:\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
