<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController;
use App\Services\Timer\BookedDeviceService;
use App\Services\Timer\SessionDeviceService;
use App\Services\Timer\DeviceTimerService;
use App\Http\Requests\Timer\CreateIndividualRequest;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Daily\Daily;
use Illuminate\Http\Request;

echo "=== Testing New Timer Creation with Activity Log ===\n\n";

try {
    // Get a daily record
    $daily = Daily::orderBy('created_at', 'desc')->first();
    if (!$daily) {
        echo "❌ No Daily records found. Creating one...\n";
        $daily = Daily::create([
            'name' => 'Test Daily ' . date('Y-m-d H:i:s'),
            'date' => now()->format('Y-m-d'),
            'status' => 1
        ]);
        echo "✅ Created Daily ID: {$daily->id}\n\n";
    }

    echo "📋 Using Daily ID: {$daily->id}\n";
    echo "Daily Date: {$daily->date}\n\n";

    // Create services
    $sessionDeviceService = new SessionDeviceService();
    $timerService = new DeviceTimerService();
    $bookedDeviceService = new BookedDeviceService();

    // Create controller
    $controller = new DeviceTimerController($sessionDeviceService, $timerService, $bookedDeviceService);

    // Prepare request data for individual timer
    $requestData = [
        'dailyId' => $daily->id,
        'deviceId' => 1, // Use device ID 1
        'deviceTypeId' => 1,
        'deviceTimeId' => 1,
        'startDateTime' => now()->format('Y-m-d H:i:s'),
        'endDateTime' => null // Open timer
    ];

    echo "🚀 Creating individual timer...\n";
    echo "Request data: " . json_encode($requestData, JSON_PRETTY_PRINT) . "\n\n";

    // Create mock request
    $request = new Request($requestData);
    $createRequest = new CreateIndividualRequest();
    $createRequest->merge($requestData);

    // Create individual timer
    $response = $controller->individualTime($createRequest);
    $responseData = $response->getData(true);

    echo "📊 Timer Creation Response:\n";
    echo "Success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
    echo "Message: " . ($responseData['message'] ?? 'N/A') . "\n\n";

    if ($responseData['success']) {
        // Get the newly created BookedDevice
        $newBookedDevice = BookedDevice::where('device_id', 1)
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($newBookedDevice) {
            echo "✅ Created BookedDevice ID: {$newBookedDevice->id}\n";
            echo "Device ID: {$newBookedDevice->device_id}\n";
            echo "Session ID: {$newBookedDevice->session_device_id}\n\n";

            // Test activity log
            echo "🔍 Testing activity log for new timer...\n";
            $activities = $bookedDeviceService->getActivityLogToDevice($newBookedDevice->id);

            echo "Activities count: " . $activities->count() . "\n\n";

            if ($activities->count() > 0) {
                echo "📝 Activities with timer_id and device_session_key:\n";
                foreach ($activities as $activity) {
                    echo "  - Event: {$activity->event}\n";
                    echo "    Log Name: {$activity->log_name}\n";
                    echo "    Description: {$activity->description}\n";
                    echo "    Created: {$activity->created_at}\n";

                    $properties = is_string($activity->properties)
                        ? json_decode($activity->properties, true)
                        : ($activity->properties ?? []);

                    if (isset($properties['timer_id'])) {
                        echo "    ✅ Timer ID: {$properties['timer_id']}\n";
                    }
                    if (isset($properties['device_session_key'])) {
                        echo "    ✅ Device Session Key: {$properties['device_session_key']}\n";
                    }
                    if (isset($properties['device_id'])) {
                        echo "    Device ID: {$properties['device_id']}\n";
                    }
                    if (isset($properties['session_type'])) {
                        echo "    Session Type: {$properties['session_type']}\n";
                    }
                    echo "    ---\n";
                }

                echo "\n🎯 Testing timer_id consistency...\n";
                $firstActivity = $activities->first();
                $firstProperties = is_string($firstActivity->properties)
                    ? json_decode($firstActivity->properties, true)
                    : ($firstActivity->properties ?? []);

                if (isset($firstProperties['timer_id'])) {
                    $expectedTimerId = 'timer_' . $newBookedDevice->device_id . '_' . $newBookedDevice->created_at->timestamp;
                    $actualTimerId = $firstProperties['timer_id'];

                    if ($actualTimerId === $expectedTimerId) {
                        echo "✅ Timer ID format is correct: {$actualTimerId}\n";
                    } else {
                        echo "❌ Timer ID format mismatch:\n";
                        echo "  Expected: {$expectedTimerId}\n";
                        echo "  Actual: {$actualTimerId}\n";
                    }
                }

            } else {
                echo "❌ No activities found for new timer\n";
            }

        } else {
            echo "❌ Could not find newly created BookedDevice\n";
        }
    } else {
        echo "❌ Timer creation failed\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== New Timer Test Complete ===\n";

?>
