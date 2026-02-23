<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Expense CRUD ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

$dailyId = 2;

// Step 1: Create Expense
echo "Step 1: Create Expense\n";

$expense = App\Models\Expense\Expense::create([
    'name' => 'كهرباء',
    'price' => 150.50,
    'daily_id' => $dailyId,
    'date' => now(),
]);

echo "✓ Expense created (ID: {$expense->id})\n";
echo "  Name: {$expense->name}\n";
echo "  Price: {$expense->price}\n\n";

// Step 2: Update Expense
echo "Step 2: Update Expense\n";

$expense->name = 'كهرباء ومياه';
$expense->price = 200.00;
$expense->save();

echo "✓ Expense updated\n";
echo "  New Name: {$expense->name}\n";
echo "  New Price: {$expense->price}\n\n";

// Step 3: Delete Expense
echo "Step 3: Delete Expense\n";

$expense->delete();

echo "✓ Expense deleted\n\n";

// Check activities
echo str_repeat('=', 80) . "\n";
echo "=== Checking Activities ===\n\n";

$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Expense\\Expense')
    ->where('subject_id', $expense->id)
    ->orderBy('id', 'asc')
    ->get();

echo "Total activities: " . $activities->count() . "\n\n";

foreach ($activities as $act) {
    echo "Activity ID: {$act->id}, Event: {$act->event}\n";
    $props = json_decode($act->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
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

// Find Expense activities
$expenseActivities = $groupedActivities->filter(function($act) use ($expense) {
    return strtolower($act->log_name) === 'expense' && $act->subject_id == $expense->id;
});

echo "Expense Activities:\n\n";

foreach ($expenseActivities as $act) {
    $resource = new App\Http\Resources\ActivityLog\Test\AllDailyActivityResource($act);
    $response = $resource->toArray(request());

    echo "Event: {$act->event}\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "✓ Test completed!\n";
