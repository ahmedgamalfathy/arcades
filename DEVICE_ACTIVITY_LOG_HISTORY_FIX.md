# Device Activity Log - Complete History After Transfer

## Problem
When a device is transferred from one session to another (e.g., from group to individual or vice versa), the activity log only showed activities from the current session. Previous activities (like orders placed before transfer) were not visible.

## Root Cause
The `getActivityLogToDevice()` method was filtering BookedDevice records by both `device_id` AND `session_device_id`:

```php
$bookedDevices = BookedDevice::where('device_id', $deviceId)
    ->where('session_device_id', $sessionDeviceId)  // ❌ This limits to current session only
    ->get();
```

This meant that when a device was transferred to a new session, all activities from the old session were excluded.

## Solution
Modified `getActivityLogToDevice()` to fetch ALL BookedDevice records for the device across all sessions:

```php
$bookedDevices = BookedDevice::where('device_id', $deviceId)
    // ✅ Removed session_device_id filter - get all sessions
    ->get();
```

Also collect all session IDs that the device was part of:

```php
$sessionIds = []; // Collect all session IDs

foreach ($bookedDevices as $bookedDevice) {
    // Get session IDs (collect all sessions this device was in)
    if ($bookedDevice->session_device_id && !in_array($bookedDevice->session_device_id, $sessionIds)) {
        $sessionIds[] = $bookedDevice->session_device_id;
    }
}
```

## How It Works

### Before Transfer
1. Device in group session (ID: 10)
2. Order placed (linked to booked_device_id: 50)
3. Activity log shows: Order created

### After Transfer
1. Device transferred to individual session (ID: 15)
2. New booked_device_id created (ID: 51)
3. Activity log shows:
   - Transfer event (from group to individual)
   - Order created (from old session) ✅
   - All other activities from both sessions ✅

## Group Session Filtering
The existing `device_id` filtering in `groupParentChildActivitiesForDevice()` ensures that in group sessions, each device only sees its own activities:

```php
->filter(function($childData) use ($deviceId, $bookedDeviceIds) {
    if ($deviceId !== null) {
        $childDeviceId = $childData['device_id'] ?? null;
        $childId = $childData['id'] ?? null;
        
        // Include if device_id matches OR if child_id is in our bookedDeviceIds
        return ($childDeviceId == $deviceId) || in_array($childId, $bookedDeviceIds);
    }
    return true;
})
```

## Example Scenario

### Timeline:
1. **10:00** - Device "PS5" added to group session "Group A"
2. **10:15** - Order #123 placed for PS5 (price: 100)
3. **10:30** - Device transferred to individual session
4. **10:45** - Order #124 placed for PS5 (price: 50)

### Activity Log Result:
```json
[
  {
    "time": "10:45",
    "eventType": "created",
    "model": {"modelName": "Order", "modelId": 124},
    "details": {"price": 50}
  },
  {
    "time": "10:30",
    "eventType": "transfer",
    "model": {"modelName": "SessionDevice", "modelId": 15},
    "details": {
      "name": {"old": "Group A", "new": "individual"}
    },
    "children": [
      {
        "modelName": "BookedDevice",
        "eventType": "transfer",
        "deviceName": "PS5"
      }
    ]
  },
  {
    "time": "10:15",
    "eventType": "created",
    "model": {"modelName": "Order", "modelId": 123},
    "details": {"price": 100}
  },
  {
    "time": "10:00",
    "eventType": "created",
    "model": {"modelName": "SessionDevice", "modelId": 10},
    "children": [
      {
        "modelName": "BookedDevice",
        "eventType": "created",
        "deviceName": "PS5"
      }
    ]
  }
]
```

## Benefits
1. Complete activity history preserved after transfers
2. Orders and expenses from old sessions remain visible
3. Transfer events clearly show session changes
4. Group session filtering still works correctly
5. No duplicate activities from other devices in group sessions

## Files Modified
- `app/Services/Timer/BookedDeviceService.php`
  - Modified `getActivityLogToDevice()` method
  - Removed `session_device_id` filter
  - Collect all session IDs dynamically

## Testing
To test the complete history:
1. Create a group session with a device
2. Place an order for that device
3. Transfer the device to an individual session
4. Place another order
5. Check activity log - should show both orders and the transfer event
