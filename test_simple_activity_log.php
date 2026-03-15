<?php

// Test the activity log method directly
echo "=== Testing Activity Log Method ===\n\n";

// Check if we can access the database directly
try {
    // Simple database connection test
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=arcade_1', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Database connection successful\n\n";

    // Check available BookedDevices
    $stmt = $pdo->prepare("SELECT id, device_id, session_device_id, status, created_at FROM booked_devices ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $allBookedDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "📋 Available BookedDevices (last 10):\n";
    foreach ($allBookedDevices as $device) {
        echo "  - ID: {$device['id']}, Device: {$device['device_id']}, Session: {$device['session_device_id']}, Status: {$device['status']}\n";
    }
    echo "\n";

    if (empty($allBookedDevices)) {
        echo "❌ No BookedDevices found in database\n";
        return;
    }

    // Use the first available BookedDevice for testing
    $testId = $allBookedDevices[0]['id'];
    echo "🧪 Testing with BookedDevice ID: $testId\n\n";

    // Check if BookedDevice exists
    $stmt = $pdo->prepare("SELECT * FROM booked_devices WHERE id = ?");
    $stmt->execute([$testId]);
    $bookedDevice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bookedDevice) {
        echo "✅ BookedDevice $testId found:\n";
        echo "  - Device ID: {$bookedDevice['device_id']}\n";
        echo "  - Session Device ID: {$bookedDevice['session_device_id']}\n";
        echo "  - Status: {$bookedDevice['status']}\n";
        echo "  - Created: {$bookedDevice['created_at']}\n\n";

        // Check activities for this BookedDevice
        $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE subject_type = 'App\\\\Models\\\\Timer\\\\BookedDevice\\\\BookedDevice' AND subject_id = ? ORDER BY created_at DESC");
        $stmt->execute([$testId]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "📋 Direct BookedDevice activities: " . count($activities) . "\n";
        foreach ($activities as $activity) {
            echo "  - {$activity['event']} at {$activity['created_at']}\n";
        }
        echo "\n";

        // Check SessionDevice activities if exists
        if ($bookedDevice['session_device_id']) {
            $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE subject_type = 'App\\\\Models\\\\Timer\\\\SessionDevice\\\\SessionDevice' AND subject_id = ? ORDER BY created_at DESC");
            $stmt->execute([$bookedDevice['session_device_id']]);
            $sessionActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "📋 SessionDevice activities: " . count($sessionActivities) . "\n";
            foreach ($sessionActivities as $activity) {
                echo "  - {$activity['event']} at {$activity['created_at']}\n";
            }
            echo "\n";
        }

        // Check today's activities
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM activity_log WHERE (subject_type = 'App\\\\Models\\\\Timer\\\\BookedDevice\\\\BookedDevice' AND subject_id = ?) OR (subject_type = 'App\\\\Models\\\\Timer\\\\SessionDevice\\\\SessionDevice' AND subject_id = ?) AND DATE(created_at) = CURDATE()");
        $stmt->execute([$testId, $bookedDevice['session_device_id']]);
        $todayCount = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "📅 Today's activities: {$todayCount['count']}\n";

    } else {
        echo "❌ BookedDevice $testId not found\n";
    }

} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

?>
