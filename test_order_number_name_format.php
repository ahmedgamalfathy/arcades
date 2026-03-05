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

echo "=== Testing Order number and name Format ===\n\n";

$order = Order::latest()->first();
$service = app(OrderService::class);

echo "Order Details:\n";
echo "- ID: {$order->id}\n";
echo "- Number: {$order->number}\n";
echo "- Name: {$order->name}\n";
echo "- Status: {$order->status}\n\n";

// Test changeOrderStatus
echo "TEST: changeOrderStatus\n";
echo str_repeat('-', 70) . "\n";

$newStatus = $order->status == 1 ? 2 : 1;
echo "Changing status: {$order->status} → {$newStatus}\n\n";

$service->changeOrderStatus($order->id, ['status' => $newStatus]);

$activity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', $order->id)
    ->orderBy('id', 'desc')
    ->first();

$resource = new AllDailyActivityResource($activity);
$result = $resource->toArray(request());

echo "Activity Log Output:\n";
echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Verification:\n";

if (isset($result['details']['number'])) {
    $numberOld = $result['details']['number']['old'];
    $numberNew = $result['details']['number']['new'];
    echo "✓ number present\n";
    echo "  old: " . json_encode($numberOld) . " (should be empty string)\n";
    echo "  new: \"{$numberNew}\"\n";

    if ($numberOld === '') {
        echo "  ✓ old is empty string as expected\n";
    } else {
        echo "  ✗ old is NOT empty string\n";
    }
}

if (isset($result['details']['name'])) {
    $nameOld = $result['details']['name']['old'];
    $nameNew = $result['details']['name']['new'];
    echo "✓ name present\n";
    echo "  old: " . json_encode($nameOld) . " (should be empty string)\n";
    echo "  new: \"{$nameNew}\"\n";

    if ($nameOld === '') {
        echo "  ✓ old is empty string as expected\n";
    } else {
        echo "  ✗ old is NOT empty string\n";
    }
}

if (isset($result['details']['deliveredStatus'])) {
    echo "✓ deliveredStatus present\n";
    echo "  old: {$result['details']['deliveredStatus']['old']}\n";
    echo "  new: {$result['details']['deliveredStatus']['new']}\n";
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "Expected Format:\n";
echo '  "number": {"old": "", "new": "ORD_XXXXX"}' . "\n";
echo '  "name": {"old": "", "new": "order name"}' . "\n";
echo str_repeat('=', 70) . "\n";
