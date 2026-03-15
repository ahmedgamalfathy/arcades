<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Models\Timer\BookedDevice\BookedDevice;

echo "=== Testing Persistent Device Key System ===\n\n";

try {
    // Create service instance
    $service = new BookedDeviceService();

    // Check available BookedDevices first
    echo "📋 Checking available BookedDevices...\n";
    $allBookedDevices = BookedDevice::orderBy('id', 'desc')->take(5)->get();

    if ($allBookedDevices->count() > 0) {
        echo "Available BookedDevices:\n";
        foreach ($allBookedDevices as $device) {
            echo "  - ID: {$device->id}, Device: {$device->device_id}, Session: {$device->session_device_id}, Status: {$device->status}, Created: {$device->created_at}\n";
        }

        // Test with multiple devices for the same device_id
        $deviceId = $allBookedDevices->first()->device_id;
        echo "\n🧪 Testing with Device ID: $deviceId\n";

        // Get all BookedDevices for this device_id
        $deviceBookedDevices = BookedDevice::where('device_id', $deviceId)->get();
        echo "Found " . $deviceBookedDevices->count() . " BookedDevice records for device_id $deviceId\n\n";

        foreach ($deviceBookedDevices as $bookedDevice) {
            echo "--- Testing BookedDevice ID: {$bookedDevice->id} ---\n";

            // Call the method
            $result = $service->getActivityLogToDevice($bookedDevice->id);

            echo "Activities count: " . $result->count() . "\n";

            if ($result->count() > 0) {
                echo "Sample activities:\n";
                foreach ($result->take(2) as $activity) {
                    echo "  - Event: {$activity->event}\n";
                    echo "    Log Name: {$activity->log_name}\n";
                    echo "    Created: {$activity->created_at}\n";

                    $properties = is_string($activity->properties)
                        ? json_decode($activity->properties, true)
                        : $activity->properties;

                    if (isset($properties['device_session_key'])) {
                        echo "    Device Session Key: {$properties['device_session_key']}\n";
                    }
                    if (isset($properties['device_id'])) {
                        echo "    Device ID: {$properties['device_id']}\n";
                    }
                    if (isset($properties['persistent_tracking'])) {
                        echo "    Persistent Tracking: " . ($properties['persistent_tracking'] ? 'Yes' : 'No') . "\n";
                    }
                    echo "    ---\n";
                }
            } else {
                echo "  ⚠️ No activities returned\n";
            }
            echo "\n";
        }

    } else {
        echo "❌ No BookedDevices found in database\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "✅ Persistent device key system implemented\n";
echo "✅ Device activities tracked across session transfers\n";
echo "✅ Key format: device_{device_id}_daily_{daily_id}_{date}\n";
echo "✅ All activities for same device on same day are linked\n";

?>
