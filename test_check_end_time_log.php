<?php

// This script helps debug the end_date_time logging issue
// Run this after calling update-end-date-time API

echo "=== Checking Activity Log Properties ===\n\n";

echo "Please run this SQL query to check the actual properties stored:\n\n";

echo "SELECT id, log_name, event, subject_id, properties \n";
echo "FROM activity_log \n";
echo "WHERE log_name = 'SessionDevice' \n";
echo "AND JSON_EXTRACT(properties, '$.children') IS NOT NULL \n";
echo "ORDER BY id DESC \n";
echo "LIMIT 5;\n\n";

echo "Look for the 'children' array in properties and check:\n";
echo "1. Does 'end_date_time' exist in the child object?\n";
echo "2. Does 'old_end_date_time' exist in the child object?\n";
echo "3. What are their values?\n\n";

echo "Example of what we expect to see:\n";
$example = [
    'children' => [
        [
            'id' => 24,
            'event' => 'updated',
            'log_name' => 'BookedDevice',
            'device_id' => 1,
            'device_type_id' => 1,
            'device_time_id' => 1,
            'status' => 1,
            'end_date_time' => '2026-02-26T08:05:00.000000Z', // or empty string
            'old_end_date_time' => '', // or a date string
        ]
    ]
];

echo json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "If old_end_date_time is missing or has wrong value, the problem is in BookedDeviceService.php\n";
echo "If old_end_date_time is correct but output is wrong, the problem is in AllDailyActivityResource.php\n";
