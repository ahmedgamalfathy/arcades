<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Models\Timer\BookedDevice\BookedDevice;

echo "=== Testing BookedDeviceService Method Directly ===\n\n";

try {
    // Create service instance
    $service = new BookedDeviceService();

    // Check available BookedDevices first
    echo "📋 Checking available BookedDevices...\n";
    $allBookedDevices = BookedDevice::orderBy('id', 'desc')->take(10)->get();

    if ($allBookedDevices->count() > 0) {
        echo "Available BookedDevices:\n";
        foreach ($allBookedDevices as $device) {
            echo "  - ID: {$device->id}, Device: {$device->device_id}, Session: {$device->session_device_id}, Status: {$device->status}\n";
        }

        // Use the first available BookedDevice
        $bookedDeviceId = $allBookedDevices->first()->id;
        echo "\n🧪 Using BookedDevice ID: $bookedDeviceId for testing\n\n";
    } else {
        echo "❌ No BookedDevices found in database\n";
        exit;
    }

    // Check if BookedDevice exists
    $bookedDevice = BookedDevice::find($bookedDeviceId);
    if (!$bookedDevice) {
        echo "❌ BookedDevice $bookedDeviceId not found\n";
        exit;
    }

    echo "✅ BookedDevice found:\n";
    echo "  - Device ID: {$bookedDevice->device_id}\n";
    echo "  - Session Device ID: {$bookedDevice->session_device_id}\n";
    echo "  - Status: {$bookedDevice->status}\n";
    echo "  - Created: {$bookedDevice->created_at}\n\n";

    // Call the method
    echo "📋 Calling getActivityLogToDevice method...\n";
    $result = $service->getActivityLogToDevice($bookedDeviceId);

    echo "✅ Method executed successfully\n";
    echo "Result type: " . gettype($result) . "\n";

    if (is_object($result) && method_exists($result, 'count')) {
        echo "Activities count: " . $result->count() . "\n";

        if ($result->count() > 0) {
            echo "\n📝 Sample activities:\n";
            foreach ($result->take(3) as $activity) {
                echo "  - Event: {$activity->event}\n";
                echo "    Log Name: {$activity->log_name}\n";
                echo "    Created: {$activity->created_at}\n";
                echo "    Subject Type: {$activity->subject_type}\n";
                echo "    Subject ID: {$activity->subject_id}\n";

                $properties = is_string($activity->properties)
                    ? json_decode($activity->properties, true)
                    : $activity->properties;

                if (isset($properties['session_key'])) {
                    echo "    Session Key: {$properties['session_key']}\n";
                }
                echo "    ---\n";
            }
        } else {
            echo "\n⚠️ No activities returned by the method\n";
        }
    } else {
        echo "Result: " . print_r($result, true) . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

?>
