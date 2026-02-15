<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Daily 2 Activity API ===\n\n";

// Get activities for daily 2
$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('daily_id', 2)
    ->orderBy('created_at', 'desc')
    ->limit(2)
    ->get();

echo "Found {$activities->count()} activities in Daily 2\n\n";

$userIds = $activities->pluck('causer_id')->unique()->filter();
$users = DB::connection('mysql')->table('users')
    ->whereIn('id', $userIds)
    ->pluck('name', 'id');

$allActivities = $activities->map(function ($act) use ($users) {
    $act->properties = json_decode($act->properties, true);
    $act->causerName = $users[$act->causer_id] ?? null;
    return $act;
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
