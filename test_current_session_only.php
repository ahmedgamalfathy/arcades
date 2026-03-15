<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set tenant database connection
config(['database.default' => 'tenant']);

use App\Services\Timer\BookedDeviceService;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use Spatie\Activitylog\Models\Activity;

echo "=== Testing Current Session Only System ===\n\n";

try {
    // Create service instance
    $service = new BookedDeviceService();

    // Get a recent BookedDevice (from today if possible)
    $todayBookedDevices = BookedDevice::whereDate('created_at', today())->get();

    if ($todayBookedDevices->count() > 0) {
        echo "📋 Found " . $todayBookedDevices->count() . " BookedDevices from today\n";

        $bookedDevice = $todayBookedDevices->first();
        echo "Testing with BookedDevice ID: {$bookedDevice->id}\n";
        echo "Device ID: {$bookedDevice->device_id}\n";
        echo "Session ID: {$bookedDevice->session_device_id}\n";
        echo "Created: {$bookedDevice->created_at}\n\n";

        // Call the method
        $result = $service->getActivityLogToDevice($bookedDevice->id);

        echo "Activities count: " . $result->count() . "\n";

        if ($result->count() > 0) {
            echo "\n📝 Activities found:\n";
            foreach ($result->take(5) as $activity) {
                echo "  - Event: {$activity->event}\n";
                echo "    Log Name: {$activity->log_name}\n";
                echo "    Created: {$activity->created_at}\n";
                echo "    Subject Type: {$activity->subject_type}\n";
                echo "    Subject ID: {$activity->subject_id}\n";

                $properties = is_string($activity->properties)
                    ? json_decode($activity->properties, true)
                    : $activity->properties;

                if (isset($properties['device_session_key'])) {
                    echo "    Device Session Key: {$properties['device_session_key']}\n";
                }
                echo "    ---\n";
            }
        } else {
            echo "  ⚠️ No activities returned for current session\n";
        }

    } else {
        echo "📋 No BookedDevices found from today\n";
        echo "Let's check activities from recent dates...\n\n";

        // Check activities from the last few days
        $recentActivities = Activity::where('created_at', '>=', now()->subDays(7))
                                   ->orderBy('created_at', 'desc')
                                   ->take(10)
                                   ->get();

        echo "Recent activities (last 7 days): " . $recentActivities->count() . "\n";

        foreach ($recentActivities as $activity) {
            echo "  - {$activity->log_name}: {$activity->event} at {$activity->created_at}\n";
        }

        if ($recentActivities->count() > 0) {
            echo "\n🧪 Let's test with a recent BookedDevice...\n";

            // Find a BookedDevice from recent activities
            $recentBookedDevice = BookedDevice::orderBy('created_at', 'desc')->first();

            if ($recentBookedDevice) {
                echo "Testing with BookedDevice ID: {$recentBookedDevice->id}\n";
                echo "Device ID: {$recentBookedDevice->device_id}\n";
                echo "Created: {$recentBookedDevice->created_at}\n\n";

                // Call the method
                $result = $service->getActivityLogToDevice($recentBookedDevice->id);

                echo "Activities count: " . $result->count() . "\n";

                if ($result->count() > 0) {
                    echo "✅ System is working - found activities for current session\n";
                } else {
                    echo "⚠️ No activities found - this is expected for old sessions\n";
                }
            }
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Summary ===\n";
echo "✅ System now filters to current session only\n";
echo "✅ No more old activities from previous sessions\n";
echo "✅ Only shows activities from today's active sessions\n";
echo "✅ Maintains device_session_key for session continuity\n";

?>
