<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing SessionDevice Activities ===\n\n";

// Get all sessions
$sessions = App\Models\Timer\SessionDevice\SessionDevice::orderBy('id', 'desc')->limit(5)->get();

echo "Recent Sessions:\n";
foreach ($sessions as $session) {
    echo "  Session {$session->id}: {$session->name}\n";
}

if ($sessions->isEmpty()) {
    echo "No sessions found!\n";
    exit;
}

// Get activities for first session
$session = $sessions->first();
echo "\n\nGetting activities for Session {$session->id}: {$session->name}\n";

$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "Found {$activities->count()} activities\n\n";

if ($activities->isEmpty()) {
    echo "No activities found for this session!\n";
    exit;
}

// Get user names
$userIds = $activities->pluck('causer_id')->unique()->filter();
$users = DB::connection('mysql')->table('users')
    ->whereIn('id', $userIds)
    ->pluck('name', 'id');

$allActivities = $activities->map(function ($activity) use ($users) {
    $activity->properties = json_decode($activity->properties, true);
    $activity->causerName = $users[$activity->causer_id] ?? null;
    return $activity;
});

// Use controller to group activities
$controller = new App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController();
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('groupParentChildActivities');
$method->setAccessible(true);

$groupedActivities = $method->invoke($controller, $allActivities);
$resource = App\Http\Resources\ActivityLog\Test\AllDailyActivityResource::collection($groupedActivities);
$response = $resource->toArray(request());

echo "API Response:\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
