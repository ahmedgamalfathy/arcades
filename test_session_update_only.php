<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing SessionDevice Update (Name Only) ===\n\n";

// Authenticate as user 1
auth()->setUser(App\Models\User::find(1));

// Create a new session
$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'name' => 'جلسة اختبار',
    'daily_id' => 2,
    'type' => 1,
]);

echo "✓ Session created: {$session->name}\n\n";

sleep(1);

// Update name and type
$session->name = 'جلسة محدثة';
$session->type = 2;
$session->save();

echo "✓ Session updated: {$session->name}, type: {$session->type}\n\n";

// Get update activity
$updateActivity = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $session->id)
    ->where('event', 'updated')
    ->orderBy('id', 'desc')
    ->first();

if ($updateActivity) {
    $userIds = collect([$updateActivity->causer_id])->filter();
    $users = DB::connection('mysql')->table('users')
        ->whereIn('id', $userIds)
        ->pluck('name', 'id');

    $updateActivity->properties = json_decode($updateActivity->properties, true);
    $updateActivity->causerName = $users[$updateActivity->causer_id] ?? null;

    $controller = new App\Http\Controllers\API\V1\Dashboard\Daily\DailyActivityController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('groupParentChildActivities');
    $method->setAccessible(true);

    $groupedActivities = $method->invoke($controller, collect([$updateActivity]));
    $resource = App\Http\Resources\ActivityLog\Test\AllDailyActivityResource::collection($groupedActivities);
    $response = $resource->toArray(request());

    echo "API Response:\n";
    echo json_encode($response[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// Clean up
$session->delete();
echo "\n✓ Session deleted (cleanup)\n";
