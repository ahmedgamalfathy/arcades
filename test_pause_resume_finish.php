<?php

require_once __DIR__ . '/vendor/autoload.php';

$baseUrl = 'https://arcade-api.testingelmo.com/api/v1/admin';
$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiNzU3YzI3YzI5YjI5YzI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YjI5YiIsImlhdCI6MTczNTI0NzU3NC4wNzU5NjUsIm5iZiI6MTczNTI0NzU3NC4wNzU5NjcsImV4cCI6MTc2Njc4MzU3NC4wNzE3NjQsInN1YiI6IjEiLCJzY29wZXMiOltdfQ.example';

function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

echo "=== Testing Pause/Resume/Finish Operations ===\n\n";

// Step 1: Create a new session with device
echo "1. Creating new session with device...\n";
$sessionData = [
    'dailyId' => 2,
    'name' => 'pause-test-session',
    'type' => 0,
    'devices' => [
        [
            'deviceId' => 21,
            'deviceTypeId' => 1,
            'deviceTimeId' => 1,
            'startDateTime' => date('Y-m-d H:i:s'),
            'endDateTime' => null
        ]
    ]
];

$createResponse = makeRequest("$baseUrl/device-timer/individual-time", 'POST', $sessionData, $token);
echo "Status: " . $createResponse['code'] . "\n";

if ($createResponse['code'] !== 200) {
    echo "Failed to create session\n";
    print_r($createResponse['body']);
    exit;
}

$sessionId = $createResponse['body']['data']['id'] ?? null;
$bookedDeviceId = $createResponse['body']['data']['booked_devices'][0]['id'] ?? null;

echo "Session ID: $sessionId\n";
echo "BookedDevice ID: $bookedDeviceId\n\n";

sleep(2);

// Step 2: Pause the device
echo "2. Pausing device...\n";
$pauseResponse = makeRequest("$baseUrl/device-timer/$bookedDeviceId/pause", 'POST', [], $token);
echo "Status: " . $pauseResponse['code'] . "\n\n";

sleep(2);

// Step 3: Check activities after pause
echo "3. Checking activities after pause...\n";
$activitiesResponse = makeRequest("$baseUrl/daily/activities?dailyId=2", 'GET', null, $token);

if ($activitiesResponse['code'] === 200) {
    $activities = $activitiesResponse['body']['data'] ?? [];

    // Find the pause activity
    $pauseActivity = null;
    foreach ($activities as $activity) {
        if ($activity['model']['modelName'] === 'SessionDevice' &&
            $activity['eventType'] === 'updated' &&
            !empty($activity['children'])) {
            // Check if this is the pause activity
            foreach ($activity['children'] as $child) {
                if ($child['modelName'] === 'BookedDevice' &&
                    isset($child['status']) &&
                    $child['status']['new'] == 2) { // PAUSED status
                    $pauseActivity = $activity;
                    break 2;
                }
            }
        }
    }

    if ($pauseActivity) {
        echo "✓ Found pause activity:\n";
        echo json_encode($pauseActivity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

        // Verify structure
        $hasChildren = !empty($pauseActivity['children']);
        $hasBookedDeviceChild = false;
        $hasCorrectStatus = false;

        if ($hasChildren) {
            foreach ($pauseActivity['children'] as $child) {
                if ($child['modelName'] === 'BookedDevice') {
                    $hasBookedDeviceChild = true;
                    if (isset($child['status']) && $child['status']['new'] == 2) {
                        $hasCorrectStatus = true;
                    }
                }
            }
        }

        echo "Verification:\n";
        echo "- Has children: " . ($hasChildren ? "✓" : "✗") . "\n";
        echo "- Has BookedDevice child: " . ($hasBookedDeviceChild ? "✓" : "✗") . "\n";
        echo "- Status changed to PAUSED (2): " . ($hasCorrectStatus ? "✓" : "✗") . "\n\n";
    } else {
        echo "✗ Pause activity not found\n\n";
    }

    // Check for separate BookedDevicePause activity (should NOT exist)
    $hasSeparatePauseActivity = false;
    foreach ($activities as $activity) {
        if ($activity['model']['modelName'] === 'BookedDevicePause') {
            $hasSeparatePauseActivity = true;
            echo "✗ Found separate BookedDevicePause activity (should not exist):\n";
            echo json_encode($activity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            break;
        }
    }

    if (!$hasSeparatePauseActivity) {
        echo "✓ No separate BookedDevicePause activity found (correct)\n\n";
    }
} else {
    echo "Failed to get activities\n";
    print_r($activitiesResponse['body']);
}

sleep(2);

// Step 4: Resume the device
echo "4. Resuming device...\n";
$resumeResponse = makeRequest("$baseUrl/device-timer/$bookedDeviceId/resume", 'POST', [], $token);
echo "Status: " . $resumeResponse['code'] . "\n\n";

sleep(2);

// Step 5: Check activities after resume
echo "5. Checking activities after resume...\n";
$activitiesResponse = makeRequest("$baseUrl/daily/activities?dailyId=2", 'GET', null, $token);

if ($activitiesResponse['code'] === 200) {
    $activities = $activitiesResponse['body']['data'] ?? [];

    // Find the resume activity
    $resumeActivity = null;
    foreach ($activities as $activity) {
        if ($activity['model']['modelName'] === 'SessionDevice' &&
            $activity['eventType'] === 'updated' &&
            !empty($activity['children'])) {
            // Check if this is the resume activity
            foreach ($activity['children'] as $child) {
                if ($child['modelName'] === 'BookedDevice' &&
                    isset($child['status']) &&
                    $child['status']['old'] == 2 && // Was PAUSED
                    $child['status']['new'] == 1) { // Now ACTIVE
                    $resumeActivity = $activity;
                    break 2;
                }
            }
        }
    }

    if ($resumeActivity) {
        echo "✓ Found resume activity:\n";
        echo json_encode($resumeActivity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo "✗ Resume activity not found\n\n";
    }
}

sleep(2);

// Step 6: Finish the device
echo "6. Finishing device...\n";
$finishResponse = makeRequest("$baseUrl/device-timer/$bookedDeviceId/finish", 'POST', [], $token);
echo "Status: " . $finishResponse['code'] . "\n\n";

sleep(2);

// Step 7: Check activities after finish
echo "7. Checking activities after finish...\n";
$activitiesResponse = makeRequest("$baseUrl/daily/activities?dailyId=2", 'GET', null, $token);

if ($activitiesResponse['code'] === 200) {
    $activities = $activitiesResponse['body']['data'] ?? [];

    // Find the finish activity
    $finishActivity = null;
    foreach ($activities as $activity) {
        if ($activity['model']['modelName'] === 'SessionDevice' &&
            $activity['eventType'] === 'updated' &&
            !empty($activity['children'])) {
            // Check if this is the finish activity
            foreach ($activity['children'] as $child) {
                if ($child['modelName'] === 'BookedDevice' &&
                    isset($child['status']) &&
                    $child['status']['new'] == 0) { // FINISHED status
                    $finishActivity = $activity;
                    break 2;
                }
            }
        }
    }

    if ($finishActivity) {
        echo "✓ Found finish activity:\n";
        echo json_encode($finishActivity, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo "✗ Finish activity not found\n\n";
    }
}

// Step 8: Cleanup - delete the session
echo "8. Cleaning up - deleting session...\n";
$deleteResponse = makeRequest("$baseUrl/device-timer/$sessionId/delete", 'DELETE', null, $token);
echo "Status: " . $deleteResponse['code'] . "\n";

echo "\n=== Test Complete ===\n";
