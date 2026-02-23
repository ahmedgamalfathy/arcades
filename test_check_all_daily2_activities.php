<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== All Activities for Daily 2 ===\n\n";

$dailyId = 2;

// Get all activities
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

echo "Total activities: " . $activities->count() . "\n\n";

// Show SessionDevice and BookedDevice activities
echo "SessionDevice Activities:\n";
foreach ($activities->where('log_name', 'SessionDevice') as $act) {
    echo "  ID: {$act->id}, Event: {$act->event}, Subject ID: {$act->subject_id}, Time: {$act->created_at}\n";
}

echo "\nBookedDevice Activities:\n";
foreach ($activities->where('log_name', 'BookedDevice') as $act) {
    $props = json_decode($act->properties, true);
    $sessionId = $props['attributes']['session_device_id'] ?? ($props['old']['session_device_id'] ?? 'N/A');
    echo "  ID: {$act->id}, Event: {$act->event}, Subject ID: {$act->subject_id}, Session: {$sessionId}, Time: {$act->created_at}\n";
}

// Now test the API with ALL activities
echo "\n" . str_repeat('=', 80) . "\n";
echo "=== Testing API Response (All Activities) ===\n\n";

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

echo "Grouped activities count: " . $groupedActivities->count() . "\n\n";

// Find SessionDevice 12 in grouped activities
$session12 = $groupedActivities->first(function($act) {
    return strtolower($act->log_name) === 'sessiondevice' && $act->subject_id == 12;
});

if ($session12) {
    echo "SessionDevice 12 found in grouped activities:\n";
    echo "  ID: {$session12->id}\n";
    echo "  Event: {$session12->event}\n";
    echo "  Children count: " . count($session12->children) . "\n\n";

    if (!empty($session12->children)) {
        echo "  Children:\n";
        foreach ($session12->children as $child) {
            echo "    - {$child->log_name} (ID: {$child->id}, Event: {$child->event})\n";
        }
    }

    // Convert to resource
    $resource = new App\Http\Resources\ActivityLog\Test\AllDailyActivityResource($session12);
    $response = $resource->toArray(request());

    echo "\n  API Response:\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "SessionDevice 12 NOT found in grouped activities!\n";
}
