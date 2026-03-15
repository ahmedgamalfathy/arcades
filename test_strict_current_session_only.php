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

echo "=== Testing Strict Current Session Only ===\n\n";

try {
    // Get a BookedDevice that might have old activities
    $bookedDevice = BookedDevice::orderBy('created_at', 'desc')->first();

    if ($bookedDevice) {
        echo "📋 Testing with BookedDevice ID: {$bookedDevice->id}\n";
        echo "Device ID: {$bookedDevice->device_id}\n";
        echo "Session ID: {$bookedDevice->session_device_id}\n";
        echo "Created: {$bookedDevice->created_at}\n";
        echo "Is from today: " . ($bookedDevice->created_at->isToday() ? 'Yes' : 'No') . "\n\n";

        // Check all activities for this device (before filtering)
        $allActivities = Activity::where(function ($query) use ($bookedDevice) {
            $query->where('subject_type', BookedDevice::class)
                  ->where('subject_id', $bookedDevice->id);
        })
        ->orWhere(function ($query) use ($bookedDevice) {
            if ($bookedDevice->session_device_id) {
                $query->where('subject_type', 'App\Models\Timer\SessionDevice\SessionDevice')
                      ->where('subject_id', $bookedDevice->session_device_id);
            }
        })
        ->orderBy('created_at', 'desc')
        ->get();

        echo "📊 All activities for this device (unfiltered): " . $allActivities->count() . "\n";

        if ($allActivities->count() > 0) {
            echo "Date range of activities:\n";
            $dates = $allActivities->pluck('created_at')->map(function ($date) {
                return $date->format('Y-m-d');
            })->unique()->values();

            foreach ($dates as $date) {
                $count = $allActivities->filter(function ($activity) use ($date) {
                    return $activity->created_at->format('Y-m-d') === $date;
                })->count();
                $isToday = $date === now()->format('Y-m-d');
                echo "  - {$date}: {$count} activities" . ($isToday ? " (TODAY)" : " (OLD)") . "\n";
            }
        }

        echo "\n🔍 Testing filtered activity log (should show current session only)...\n";

        // Test the service method
        $service = new BookedDeviceService();
        $filteredActivities = $service->getActivityLogToDevice($bookedDevice->id);

        echo "📊 Filtered activities count: " . $filteredActivities->count() . "\n\n";

        if ($filteredActivities->count() > 0) {
            echo "📝 Filtered activities (should be current session only):\n";
            $allFromToday = true;

            foreach ($filteredActivities as $activity) {
                $isToday = $activity->created_at->isToday();
                if (!$isToday) {
                    $allFromToday = false;
                }

                echo "  - Event: {$activity->event}\n";
                echo "    Log Name: {$activity->log_name}\n";
                echo "    Date: {$activity->created_at->format('Y-m-d H:i:s')}\n";
                echo "    Is Today: " . ($isToday ? 'YES' : 'NO') . "\n";

                $properties = is_string($activity->properties)
                    ? json_decode($activity->properties, true)
                    : ($activity->properties ?? []);

                if (isset($properties['timer_id'])) {
                    echo "    Timer ID: {$properties['timer_id']}\n";
                }
                if (isset($properties['current_session_only'])) {
                    echo "    Current Session Only: {$properties['current_session_only']}\n";
                }
                echo "    ---\n";
            }

            echo "\n🎯 Verification Results:\n";
            if ($allFromToday) {
                echo "✅ ALL activities are from today (current session)\n";
            } else {
                echo "❌ Some activities are from previous days (OLD DATA SHOWING)\n";
            }

            // Check if we have both old and new data
            $todayCount = $allActivities->filter(function ($activity) {
                return $activity->created_at->isToday();
            })->count();

            $oldCount = $allActivities->count() - $todayCount;

            echo "\nData Summary:\n";
            echo "  - Total activities in DB: {$allActivities->count()}\n";
            echo "  - Today's activities: {$todayCount}\n";
            echo "  - Old activities: {$oldCount}\n";
            echo "  - Filtered result: {$filteredActivities->count()}\n";

            if ($oldCount > 0 && $filteredActivities->count() === $todayCount) {
                echo "✅ PERFECT: Old activities filtered out successfully\n";
            } elseif ($oldCount > 0 && $filteredActivities->count() > $todayCount) {
                echo "❌ PROBLEM: Old activities are still showing\n";
            } elseif ($filteredActivities->count() === 0 && $todayCount === 0) {
                echo "ℹ️ INFO: No activities for today (expected for old devices)\n";
            }

        } else {
            echo "  ⚠️ No activities returned\n";
            echo "  This is expected if the device has no current session activities\n";
        }

    } else {
        echo "❌ No BookedDevices found in database\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Strict Current Session Test Complete ===\n";
echo "\n🎯 EXPECTED BEHAVIOR:\n";
echo "- Should show ONLY activities from current active session\n";
echo "- Should NOT show any activities from previous days\n";
echo "- Should filter out all old logs completely\n";

?>
