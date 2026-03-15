<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Models\Timer\BookedDevice\BookedDevice;

echo "=== Testing Activity Log Endpoint ===\n\n";

try {
    // Get a recent BookedDevice
    $bookedDevice = BookedDevice::orderBy('created_at', 'desc')->first();

    if ($bookedDevice) {
        echo "📋 Testing with BookedDevice ID: {$bookedDevice->id}\n";
        echo "Device ID: {$bookedDevice->device_id}\n";
        echo "Session ID: {$bookedDevice->session_device_id}\n";
        echo "Created: {$bookedDevice->created_at}\n\n";

        // Create service instance directly
        $service = new BookedDeviceService();

        // Call the service method directly
        $activities = $service->getActivityLogToDevice($bookedDevice->id);

        echo "📊 Service Response:\n";
        echo "Activities count: " . $activities->count() . "\n\n";

        if ($activities->count() > 0) {
            echo "📝 Sample activities:\n";
            foreach ($activities->take(3) as $activity) {
                echo "  - Event: " . ($activity->event ?? 'N/A') . "\n";
                echo "    Log Name: " . ($activity->log_name ?? 'N/A') . "\n";
                echo "    Description: " . ($activity->description ?? 'N/A') . "\n";
                echo "    Created: " . ($activity->created_at ?? 'N/A') . "\n";

                $properties = is_string($activity->properties)
                    ? json_decode($activity->properties, true)
                    : ($activity->properties ?? []);

                if (isset($properties['timer_id'])) {
                    echo "    Timer ID: {$properties['timer_id']}\n";
                }
                if (isset($properties['device_session_key'])) {
                    echo "    Device Session Key: {$properties['device_session_key']}\n";
                }
                if (isset($properties['device_id'])) {
                    echo "    Device ID: {$properties['device_id']}\n";
                }
                echo "    ---\n";
            }
        } else {
            echo "  ⚠️ No activities returned\n";
            echo "  This is expected for old data without device_session_key\n";
        }

    } else {
        echo "❌ No BookedDevices found in database\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Activity Log Endpoint Test Complete ===\n";

?>
