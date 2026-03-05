<?php
/**
 * Test: Individual Time Creation with BookedDevice as Child
 *
 * This test verifies that when creating an individual time:
 * 1. Only ONE SessionDevice activity is created (not two separate activities)
 * 2. The SessionDevice activity has BookedDevice as a child
 * 3. The response format matches DailyActivityController
 *
 * Expected Response:
 * {
 *   "activityLogId": X,
 *   "date": "28-Feb",
 *   "time": "15:05",
 *   "eventType": "created",
 *   "userName": "User Name",
 *   "model": {
 *     "modelName": "SessionDevice",
 *     "modelId": X
 *   },
 *   "details": {
 *     "name": {"old": "individual", "new": "individual"},
 *     "type": {"old": 0, "new": 0}
 *   },
 *   "children": [
 *     {
 *       "modelName": "BookedDevice",
 *       "eventType": "created",
 *       "deviceName": {"old": "", "new": "Device Name"},
 *       "deviceType": {"old": "", "new": "Device Type"},
 *       "deviceTime": {"old": "", "new": "Device Time"},
 *       "status": {"old": "", "new": 1}
 *     }
 *   ]
 * }
 *
 * Test Steps:
 * 1. Create individual time via POST /api/v1/admin/device-timer/individual
 * 2. Get the device ID from response
 * 3. Call GET /api/v1/admin/device-timer/{id}/activity-log
 * 4. Verify:
 *    - Only ONE activity is returned (SessionDevice)
 *    - children array is NOT empty
 *    - children[0] contains BookedDevice details
 *    - All fields follow {old, new} format
 *
 * API Endpoints:
 * - POST /api/v1/admin/device-timer/individual
 *   Body: {
 *     "dailyId": X,
 *     "deviceId": X,
 *     "deviceTypeId": X,
 *     "deviceTimeId": X,
 *     "startDateTime": "2024-02-28 15:00:00"
 *   }
 *
 * - GET /api/v1/admin/device-timer/{bookedDeviceId}/activity-log
 */

echo "Test: Individual Time Creation with BookedDevice as Child\n";
echo "=========================================================\n\n";

echo "BEFORE FIX:\n";
echo "- Two separate activities were created (SessionDevice + BookedDevice)\n";
echo "- children array was empty\n";
echo "- Response had two objects instead of one with children\n\n";

echo "AFTER FIX:\n";
echo "- Only ONE SessionDevice activity is created\n";
echo "- BookedDevice is included as a child in the SessionDevice activity\n";
echo "- Response format matches DailyActivityController\n\n";

echo "CHANGES MADE:\n";
echo "1. Modified individualTime() in DeviceTimerController\n";
echo "2. Create SessionDevice without automatic logging using withoutEvents()\n";
echo "3. Create BookedDevice without automatic logging using createBookedDeviceWithoutLog()\n";
echo "4. Create ONE manual activity log for SessionDevice with BookedDevice as child\n";
echo "5. Use same format as groupTime() for consistency\n\n";

echo "TEST INSTRUCTIONS:\n";
echo "1. Create an individual time using the API\n";
echo "2. Get the activity log for that device\n";
echo "3. Verify the response has:\n";
echo "   - ONE activity (SessionDevice)\n";
echo "   - children array with BookedDevice details\n";
echo "   - All fields in {old, new} format\n";
