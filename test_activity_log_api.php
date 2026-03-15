<?php

// Simple test to check if BookedDevice exists and has activities
echo "Testing Activity Log API for BookedDevice ID 21\n\n";

// Test the API endpoint directly
$url = "http://localhost/arcades/api/v1/admin/device-timer/21/activity-log";

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            echo "✅ API call successful\n";
            echo "Data count: " . count($data['data'] ?? []) . "\n";
            if (!empty($data['data'])) {
                echo "Sample activity:\n";
                print_r($data['data'][0]);
            } else {
                echo "⚠️ No activities returned - this might be the issue\n";
            }
        } else {
            echo "❌ API returned error: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ Invalid JSON response\n";
    }
} else {
    echo "❌ HTTP Error: $httpCode\n";
}

?>
