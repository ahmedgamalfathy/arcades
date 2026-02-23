<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Delete BookedDevice from Session ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Get Session 5 with its devices
$session = App\Models\Timer\SessionDevice\SessionDevice::with('bookedDevices')->find(5);

if (!$session) {
    echo "Session 5 not found!\n";
    exit;
}

echo "Session: {$session->name} (ID: {$session->id})\n";
echo "BookedDevices count: {$session->bookedDevices->count()}\n\n";

if ($session->bookedDevices->isEmpty()) {
    echo "No devices to delete!\n";
    exit;
}

$bookedDevice = $session->bookedDevices->first();
echo "Deleting BookedDevice ID: {$bookedDevice->id}\n";
echo "  Device Type: " . ($bookedDevice->deviceType?->name ?? 'N/A') . "\n";
echo "  Device Time: " . ($bookedDevice->deviceTime?->name ?? 'N/A') . "\n";
echo "  Status: {$bookedDevice->status}\n\n";

// Delete the device
$bookedDevice->delete();
echo "✓ BookedDevice deleted!\n\n";

// Check delete activity
$deleteActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->where('event', 'deleted')
    ->orderBy('id', 'desc')
    ->first();

if ($deleteActivity) {
    echo "Delete Activity found (ID: {$deleteActivity->id})\n";
    echo "Daily ID: {$deleteActivity->daily_id}\n\n";

    $props = json_decode($deleteActivity->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Test API Response
echo str_repeat('=', 80) . "\n";
echo "=== API Response ===\n\n";

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

echo "Last 2 Activities:\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
