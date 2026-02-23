<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking Database Tables ===\n\n";

// Check sessions
$sessionsCount = DB::connection('tenant')->table('session_devices')->count();
echo "Sessions (session_devices): {$sessionsCount}\n";

// Check orders
$ordersCount = DB::connection('tenant')->table('orders')->count();
echo "Orders: {$ordersCount}\n";

// Check expenses
$expensesCount = DB::connection('tenant')->table('expenses')->count();
echo "Expenses: {$expensesCount}\n";

// Check dailies
$dailiesCount = DB::connection('tenant')->table('dailies')->count();
echo "Dailies: {$dailiesCount}\n";

// Check activity log
$activitiesCount = DB::connection('tenant')->table('activity_log')->count();
echo "Activity Log: {$activitiesCount}\n";

// Check activity log by model
echo "\n=== Activity Log by Model ===\n";
$activityByModel = DB::connection('tenant')
    ->table('activity_log')
    ->select('log_name', DB::raw('count(*) as count'))
    ->groupBy('log_name')
    ->get();

foreach ($activityByModel as $row) {
    echo "{$row->log_name}: {$row->count}\n";
}
