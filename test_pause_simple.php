<?php

require_once __DIR__ . '/vendor/autoload.php';

$baseUrl = 'https://arcade-api.testingelmo.com/api/v1/admin';

// Use an existing BookedDevice ID that's currently ACTIVE
$bookedDeviceId = 21; // Replace with actual ID from your system

echo "=== Testing Pause Operation ===\n\n";

echo "Testing with BookedDevice ID: $bookedDeviceId\n";
echo "Expected behavior:\n";
echo "1. Create ONE SessionDevice activity with 'updated' event\n";
echo "2. BookedDevice should appear as child with status change\n";
echo "3. NO separate BookedDevicePause activity should be created\n\n";

echo "Please manually:\n";
echo "1. Call: POST $baseUrl/device-timer/$bookedDeviceId/pause\n";
echo "2. Check activities for daily_id=2\n";
echo "3. Verify the structure matches expected behavior\n\n";

echo "Expected JSON structure:\n";
$expected = [
    'activityLogId' => 'X',
    'date' => 'DD-MMM',
    'time' => 'HH:MM AM/PM',
    'eventType' => 'updated',
    'userName' => 'User Name',
    'model' => [
        'modelName' => 'SessionDevice',
        'modelId' => 'Y'
    ],
    'details' => [
        // SessionDevice details (may be empty if no fields changed)
    ],
    'children' => [
        [
            'modelName' => 'BookedDevice',
            'eventType' => 'updated',
            'deviceName' => ['old' => 'device-name', 'new' => 'device-name'],
            'deviceType' => ['old' => 'type-name', 'new' => 'type-name'],
            'deviceTime' => ['old' => 'time-name', 'new' => 'time-name'],
            'status' => ['old' => 1, 'new' => 2] // ACTIVE -> PAUSED
        ]
    ]
];

echo json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Verification Checklist ===\n";
echo "[ ] Only ONE activity created for the pause action\n";
echo "[ ] Activity log_name is 'SessionDevice'\n";
echo "[ ] Activity event is 'updated'\n";
echo "[ ] Activity has children array with one BookedDevice\n";
echo "[ ] BookedDevice child shows status change from 1 to 2\n";
echo "[ ] NO separate BookedDevicePause activity exists\n";
echo "[ ] All device info (name, type, time) is present\n\n";

echo "Same applies for:\n";
echo "- Resume: POST $baseUrl/device-timer/$bookedDeviceId/resume (status 2->1)\n";
echo "- Finish: POST $baseUrl/device-timer/$bookedDeviceId/finish (status 1->0)\n";
