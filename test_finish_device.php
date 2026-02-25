<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Testing Finish BookedDevice ===\n\n";

// Authenticate
auth()->setUser(App\Models\User::find(1));

// Create a new device to test
echo "Step 1: Create Individual Session with Device\n";

$session = App\Models\Timer\SessionDevice\SessionDevice::create([
    'type' => 0,
    'name' => 'Test Finish',
    'daily_id' => 2,
]);

$bookedDevice = App\Models\Timer\BookedDevice\BookedDevice::create([
    'session_device_id' => $session->id,
    'device_id' => 1,
    'device_type_id' => 1,
    'device_time_id' => 1,
    'status' => 1, // Active
    'start_date_time' => now()->subMinutes(30),
]);

echo "✓ Device created (ID: {$bookedDevice->id})\n";
echo "  Status: {$bookedDevice->status} (Active)\n";
echo "  Start: {$bookedDevice->start_date_time}\n\n";

// Wait a moment
sleep(1);

echo "Step 2: Finish Device\n";

$service = new App\Services\Timer\DeviceTimerService(
    new App\Services\Timer\BookedDeviceService(),
    new App\Services\Timer\BookedDevicePauseService()
);

$finished = $service->finish($bookedDevice->id, ['actualPaidAmount' => 50.00]);

echo "✓ Device finished\n";
echo "  Status: {$finished->status} (Finished)\n";
echo "  End: {$finished->end_date_time}\n";
echo "  Period Cost: {$finished->period_cost}\n";
echo "  Actual Paid: {$finished->actual_paid_amount}\n\n";

// Check activities
echo str_repeat('=', 80) . "\n";
echo "=== Checking Activities ===\n\n";

$activities = DB::connection('tenant')
    ->table('activity_log')
    ->where('subject_type', 'App\\Models\\Timer\\BookedDevice\\BookedDevice')
    ->where('subject_id', $bookedDevice->id)
    ->orderBy('id', 'asc')
    ->get();

echo "Total activities: " . $activities->count() . "\n\n";

foreach ($activities as $act) {
    echo "Activity ID: {$act->id}, Event: {$act->event}, Time: {$act->created_at}\n";
    $props = json_decode($act->properties, true);
    echo "Properties:\n";
    echo json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

if ($activities->count() == 0) {
    echo "⚠ No activities found! This is the problem.\n\n";
}

echo "✓ Test completed!\n";
