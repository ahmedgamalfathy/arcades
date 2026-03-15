<?php

require_once 'vendor/autoload.php';

use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Timer\SessionDevice\SessionDevice;
use Spatie\Activitylog\Models\Activity;

echo "=== Debug Activity Log for BookedDevice ID 40 ===\n\n";

// Check if BookedDevice exists
$bookedDevice = BookedDevice::find(40);
if (!$bookedDevice) {
    echo "❌ BookedDevice with ID 40 not found\n";
    exit;
}

echo "✅ BookedDevice found:\n";
echo "  - ID: {$bookedDevice->id}\n";
echo "  - Device ID: {$bookedDevice->device_id}\n";
echo "  - Session Device ID: {$bookedDevice->session_device_id}\n";
echo "  - Status: {$bookedDevice->status}\n";
echo "  - Created: {$bookedDevice->created_at}\n";

// Check SessionDevice
$sessionDevice = null;
if ($bookedDevice->session_device_id) {
    $sessionDevice = SessionDevice::find($bookedDevice->session_device_id);
    if ($sessionDevice) {
        echo "  - Session Name: {$sessionDevice->name}\n";
        echo "  - Session Type: {$sessionDevice->type}\n";
        echo "  - Session Created: {$sessionDevice->created_at}\n";
    } else {
        echo "  - ⚠️ SessionDevice not found\n";
    }
}

echo "\n";

// Check all activities for this BookedDevice
echo "=== All Activities for BookedDevice ID 40 ===\n";
$allActivities = Activity::where('subject_type', BookedDevice::class)
    ->where('subject_id', 40)
    ->orderBy('created_at', 'desc')
    ->get();

echo "Total activities found: " . $allActivities->count() . "\n";

foreach ($allActivities as $activity) {
    echo "- Activity ID: {$activity->id}\n";
    echo "  Event: {$activity->event}\n";
    echo "  Log Name: {$activity->log_name}\n";
    echo "  Created: {$activity->created_at}\n";
    echo "  Properties: " . json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n";
    echo "  ---\n";
}

echo "\n";

// Check activities for the device_id (all BookedDevices with same device_id)
echo "=== All Activities for Device ID {$bookedDevice->device_id} ===\n";
$deviceActivities = Activity::where('subject_type', BookedDevice::class)
    ->whereIn('subject_id', function($query) use ($bookedDevice) {
        $query->select('id')
              ->from('booked_devices')
              ->where('device_id', $bookedDevice->device_id);
    })
    ->orderBy('created_at', 'desc')
    ->get();

echo "Total device activities found: " . $deviceActivities->count() . "\n";

foreach ($deviceActivities as $activity) {
    echo "- Activity ID: {$activity->id}\n";
    echo "  Subject ID: {$activity->subject_id}\n";
    echo "  Event: {$activity->event}\n";
    echo "  Created: {$activity->created_at}\n";
    echo "  Is Today: " . ($activity->created_at->isToday() ? 'Yes' : 'No') . "\n";
    echo "  ---\n";
}

echo "\n";

// Check SessionDevice activities
if ($sessionDevice) {
    echo "=== SessionDevice Activities ===\n";
    $sessionActivities = Activity::where('subject_type', SessionDevice::class)
        ->where('subject_id', $sessionDevice->id)
        ->orderBy('created_at', 'desc')
        ->get();

    echo "Total session activities found: " . $sessionActivities->count() . "\n";

    foreach ($sessionActivities as $activity) {
        echo "- Activity ID: {$activity->id}\n";
        echo "  Event: {$activity->event}\n";
        echo "  Created: {$activity->created_at}\n";
        echo "  Is Today: " . ($activity->created_at->isToday() ? 'Yes' : 'No') . "\n";
        echo "  Properties: " . json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n";
        echo "  ---\n";
    }
}

echo "\n";

// Test the session key generation
echo "=== Session Key Generation Test ===\n";
if ($sessionDevice) {
    if ($sessionDevice->type == 0) { // Individual
        $sessionKey = 'individual_' . $bookedDevice->device_id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s');
    } else { // Group
        $sessionKey = 'session_' . $sessionDevice->id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s');
    }
    echo "Generated session key: {$sessionKey}\n";
} else {
    $sessionKey = 'individual_' . $bookedDevice->device_id . '_' . $bookedDevice->created_at->format('Y-m-d_H-i-s');
    echo "Generated session key (fallback): {$sessionKey}\n";
}

echo "\n";

// Test the query that should be used
echo "=== Testing New Query Logic ===\n";
$testActivities = Activity::where(function ($query) use ($sessionKey, $bookedDevice) {
    $query->where(function ($q) use ($sessionKey) {
        // Get activities that have the same session_key
        $q->whereJsonContains('properties->session_key', $sessionKey);
    })
    ->orWhere(function ($q) use ($bookedDevice) {
        // Also include direct activities for this specific booked device
        // that might not have session_key yet (for backward compatibility)
        $q->where('subject_type', BookedDevice::class)
          ->where('subject_id', $bookedDevice->id)
          ->whereDate('created_at', today()); // Only today's activities
    });
})
->orderBy('created_at', 'desc')
->get();

echo "Activities found with new logic: " . $testActivities->count() . "\n";

foreach ($testActivities as $activity) {
    echo "- Activity ID: {$activity->id}\n";
    echo "  Subject Type: {$activity->subject_type}\n";
    echo "  Subject ID: {$activity->subject_id}\n";
    echo "  Event: {$activity->event}\n";
    echo "  Created: {$activity->created_at}\n";
    echo "  Has session_key: " . (isset($activity->properties['session_key']) ? 'Yes' : 'No') . "\n";
    if (isset($activity->properties['session_key'])) {
        echo "  Session Key: {$activity->properties['session_key']}\n";
    }
    echo "  ---\n";
}

echo "\n=== Debug Complete ===\n";

?>
