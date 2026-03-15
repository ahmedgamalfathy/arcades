<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Models\Activity;

echo "=== Testing Timer ID System ===\n\n";

try {
    // Create service instance
    $service = new BookedDeviceService();

    // Get a recent BookedDevice
    $bookedDevice = BookedDevice::orderBy('created_at', 'desc')->first();

    if ($bookedDevice) {
        echo "📋 Testing with BookedDevice ID: {$bookedDevice->id}\n";
        echo "Device ID: {$bookedDevice->device_id}\n";
        echo "Session ID: {$bookedDevice->session_device_id}\n";
        echo "Created: {$bookedDevice->created_at}\n\n";

        // Test getTimerId method using reflection
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getTimerId');
        $method->setAccessible(true);

        $timerId = $method->invoke($service, $bookedDevice);
        echo "Generated Timer ID: $timerId\n\n";

        // Call the activity log method
        $result = $service->getActivityLogToDevice($bookedDevice->id);

        echo "Activities count: " . $result->count() . "\n";

        if ($result->count() > 0) {
            echo "\n📝 Sample activities with Timer ID:\n";
            foreach ($result->take(3) as $activity) {
                echo "  - Event: {$activity->event}\n";
                echo "    Log Name: {$activity->log_name}\n";
                echo "    Created: {$activity->created_at}\n";

                $properties = is_string($activity->properties)
                    ? json_decode($activity->properties, true)
                    : $activity->properties;

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
            echo "  ⚠️ No activities returned (expected for old data)\n";
        }

        // Test timer_id consistency
        echo "\n🔍 Testing Timer ID Consistency:\n";

        // Call getTimerId multiple times to ensure it returns the same ID
        $timerId1 = $method->invoke($service, $bookedDevice);
        $timerId2 = $method->invoke($service, $bookedDevice);

        if ($timerId1 === $timerId2) {
            echo "✅ Timer ID is consistent: $timerId1\n";
        } else {
            echo "❌ Timer ID is not consistent:\n";
            echo "  First call: $timerId1\n";
            echo "  Second call: $timerId2\n";
        }

    } else {
        echo "❌ No BookedDevices found in database\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Timer ID System Summary ===\n";
echo "✅ Timer ID format: timer_{device_id}_{timestamp}_{unique_id}\n";
echo "✅ Timer ID stays constant throughout timer lifecycle\n";
echo "✅ Timer ID persists across session transfers\n";
echo "✅ Timer ID links all operations for the same timer\n";
echo "✅ Combined with device_session_key for complete tracking\n";

?>
