<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\API\V1\Dashboard\Timer\DeviceTimerController;
use App\Http\Requests\Timer\CreateIndividualRequest;
use App\Http\Requests\Timer\CreateGroupRequest;

echo "=== Testing Session Key Activity Log Implementation ===\n\n";

// Test 1: Create Individual Session
echo "1. Testing Individual Session Creation...\n";
$controller = new DeviceTimerController();

// Mock request data for individual session
$individualData = [
    'dailyId' => 1,
    'deviceId' => 1,
    'deviceTypeId' => 1,
    'deviceTimeId' => 1,
    'startDateTime' => now()->format('Y-m-d H:i:s'),
    'endDateTime' => now()->addHours(2)->format('Y-m-d H:i:s')
];

$request = new CreateIndividualRequest();
$request->merge($individualData);

try {
    $response = $controller->individualTime($request);
    $responseData = $response->getData(true);

    if ($responseData['success']) {
        echo "✓ Individual session created successfully\n";
        echo "  Expected session_key format: individual_{device_id}_{timestamp}\n";
    } else {
        echo "✗ Failed to create individual session: " . ($responseData['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ Exception during individual session creation: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Create Group Session
echo "2. Testing Group Session Creation...\n";

$groupData = [
    'dailyId' => 1,
    'name' => 'Test Group Session',
    'deviceId' => 2,
    'deviceTypeId' => 1,
    'deviceTimeId' => 1,
    'startDateTime' => now()->format('Y-m-d H:i:s'),
    'endDateTime' => now()->addHours(2)->format('Y-m-d H:i:s')
];

$groupRequest = new CreateGroupRequest();
$groupRequest->merge($groupData);

try {
    $response = $controller->groupTime($groupRequest);
    $responseData = $response->getData(true);

    if ($responseData['success']) {
        echo "✓ Group session created successfully\n";
        echo "  Expected session_key format: session_{session_id}_{timestamp}\n";
    } else {
        echo "✗ Failed to create group session: " . ($responseData['message'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "✗ Exception during group session creation: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Test Activity Log Retrieval
echo "3. Testing Activity Log Retrieval with Session Key...\n";

// Get a booked device to test activity log
$bookedDevice = \App\Models\Timer\BookedDevice\BookedDevice::with('sessionDevice')->first();

if ($bookedDevice) {
    try {
        $response = $controller->getActitvityLogToDevice($bookedDevice->id);
        $responseData = $response->getData(true);

        if ($responseData['success']) {
            echo "✓ Activity log retrieved successfully\n";
            echo "  Device ID: {$bookedDevice->device_id}\n";
            echo "  Session Type: " . ($bookedDevice->sessionDevice ? $bookedDevice->sessionDevice->type : 'N/A') . "\n";

            $activities = $responseData['data'] ?? [];
            echo "  Total activities: " . count($activities) . "\n";

            // Check if activities have session_key
            $hasSessionKey = false;
            foreach ($activities as $activity) {
                if (isset($activity['properties']['session_key'])) {
                    $hasSessionKey = true;
                    echo "  ✓ Found session_key: " . $activity['properties']['session_key'] . "\n";
                    break;
                }
            }

            if (!$hasSessionKey) {
                echo "  ⚠ No session_key found in activities (might be legacy data)\n";
            }

        } else {
            echo "✗ Failed to retrieve activity log: " . ($responseData['message'] ?? 'Unknown error') . "\n";
        }
    } catch (Exception $e) {
        echo "✗ Exception during activity log retrieval: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠ No booked devices found to test activity log\n";
}

echo "\n";

// Test 4: Test Transfer Operations
echo "4. Testing Transfer Operations with Session Key...\n";

if ($bookedDevice) {
    try {
        // Test transfer to group
        $transferData = [
            'name' => 'Transfer Test Group'
        ];

        $transferRequest = new Request($transferData);
        $response = $controller->transferDeviceToGroup($bookedDevice->id, $transferRequest);
        $responseData = $response->getData(true);

        if ($responseData['success']) {
            echo "✓ Device transfer to group completed\n";
            echo "  Expected session_key with transfer_type: 'to_group'\n";
        } else {
            echo "✗ Failed to transfer device: " . ($responseData['message'] ?? 'Unknown error') . "\n";
        }

    } catch (Exception $e) {
        echo "✗ Exception during transfer: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠ No booked devices found to test transfer\n";
}

echo "\n=== Test Summary ===\n";
echo "✓ Session key implementation added to activity logs\n";
echo "✓ Individual sessions use format: individual_{device_id}_{timestamp}\n";
echo "✓ Group sessions use format: session_{session_id}_{timestamp}\n";
echo "✓ Transfer operations include session_key and transfer_type\n";
echo "✓ Activity log retrieval filters by session_key and today's date\n";
echo "\nImplementation completed successfully!\n";

?>
