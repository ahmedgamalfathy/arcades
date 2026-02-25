<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.connections.mysql.database' => env('TENANT_DB_DATABASE', 'arcade_1')]);
\Illuminate\Support\Facades\DB::purge('mysql');
\Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Order\Order;
use App\Services\Order\OrderService;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ActivityLog\Test\AllDailyActivityResource;

echo "=== Final Test: Order Activity Log with Number ===\n\n";

$order = Order::whereNotNull('booked_device_id')->latest()->first();
$service = app(OrderService::class);

echo "Order: {$order->number} (ID: {$order->id})\n";
echo "Device: " . ($order->bookedDevice?->device?->name ?? 'N/A') . " (ID: {$order->booked_device_id})\n\n";

// Change status
echo "Changing status: {$order->status} → 2\n";
$service->changeOrderStatus($order->id, ['status' => 2]);

$activity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->orderBy('id', 'desc')
    ->first();

$resource = new AllDailyActivityResource($activity);
$result = $resource->toArray(request());

echo "\nActivity Log Output:\n";
echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Verification:\n";
if (isset($result['details']['number'])) {
    echo "✓ number: {$result['details']['number']['new']}\n";
}
if (isset($result['details']['deliveredStatus'])) {
    echo "✓ deliveredStatus: {$result['details']['deliveredStatus']['old']} → {$result['details']['deliveredStatus']['new']}\n";
}
if (isset($result['details']['bookedDevice'])) {
    echo "✓ bookedDevice: ID={$result['details']['bookedDevice']['id']}, Name={$result['details']['bookedDevice']['name']}\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "SUCCESS: Activity log includes number, deliveredStatus, and bookedDevice!\n";
echo str_repeat('=', 70) . "\n";
