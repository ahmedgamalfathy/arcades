<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Switch to tenant database
config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Order\Order;
use App\Services\Order\OrderService;
use Spatie\Activitylog\Models\Activity;

echo "=== Checking Raw Activity Properties ===\n\n";

$order = Order::latest()->first();
$orderService = app(OrderService::class);

echo "Changing payment status...\n";
$order->refresh();
$orderService->changeOrderPaymentStatus($order->id, ['isPaid' => !$order->is_paid]);

$activity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->latest()
    ->first();

echo "\nRaw Activity Properties:\n";
echo json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
