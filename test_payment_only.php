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

$order = Order::find(34);
$service = app(OrderService::class);

echo "Before: is_paid = " . ($order->is_paid ? 'true' : 'false') . "\n";
echo "Changing to: false\n\n";

$service->changeOrderPaymentStatus(34, ['isPaid' => false]);

$activity = Activity::where('subject_type', 'App\Models\Order\Order')
    ->where('subject_id', 34)
    ->orderBy('id', 'desc')
    ->first();

echo "Activity ID: {$activity->id}\n";
echo "Created At: {$activity->created_at}\n\n";

echo "Raw Properties:\n";
print_r($activity->properties);

echo "\nResource Output:\n";
$resource = new AllDailyActivityResource($activity);
$result = $resource->toArray(request());
echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
