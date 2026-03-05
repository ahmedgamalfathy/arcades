# Keep Old Sessions for Activity Log History

## Problem
When transferring a device from one session to another, the old session was being deleted if it became empty. This caused the activity log to lose the history of the old session creation and all activities related to it.

### Example:
1. Device added to group session "test" at 10:16
2. Order placed at 09:55
3. Device transferred to individual session at 10:18
4. **Result**: The "تم تحديث جلسة" (session created) activity disappeared from the log

## Root Cause
Both `transferBookedDeviceToSessionDevice()` and `transferDeviceToGroup()` methods were deleting empty sessions after transfer:

```php
// ❌ Old code - deletes the session
if ($oldSessionDevice && $oldSessionDevice->bookedDevices()->count() == 0) {
    $oldSessionDevice->withoutEvents(function () use ($oldSessionDevice) {
        $oldSessionDevice->delete();
    });
}
```

When the session is deleted (soft deleted), it becomes harder to track and the activity log loses context.

## Solution
**Don't delete old sessions** - keep them for activity log history:

```php
// ✅ New code - keep the session for history
if ($oldSessionDevice && $oldSessionDevice->bookedDevices()->count() == 0) {
    // Don't delete - just leave it as is for history
    // The session remains in the database for historical reference
}
```

## Benefits

### 1. Complete Activity History
All activities remain visible even after transfers:
- Session creation
- Device additions
- Orders placed
- Transfers between sessions

### 2. Better Audit Trail
Users can see the complete timeline:
```
10:18 - Device transferred from "test" to "individual"
09:55 - Order #123 created (price: 40)
10:16 - Session "test" created with device
```

### 3. No Data Loss
- Old sessions remain in database
- All relationships preserved
- Activity log queries work correctly

## Implementation Details

### Modified Methods:
1. **transferBookedDeviceToSessionDevice()** (group → individual)
   - Removed session deletion code
   - Added comment explaining why we keep the session

2. **transferDeviceToGroup()** (individual → group)
   - Removed session deletion code
   - Added comment explaining why we keep the session

### Database Impact:
- Empty sessions will remain in the `session_devices` table
- This is acceptable because:
  - They provide historical context
  - Database size impact is minimal
  - They can be cleaned up later if needed (with proper archiving)

## Activity Log Query
The `getActivityLogToDevice()` method now:
1. Fetches all BookedDevice records for the device (across all sessions)
2. Collects all session IDs the device was part of
3. Fetches activities for all related sessions
4. Shows complete history including old sessions

```php
// Get ALL BookedDevice records for this device (across all sessions)
$bookedDevices = BookedDevice::where('device_id', $deviceId)
    ->with(['orders', 'sessionDevice', 'pauses'])
    ->get();

// Collect all session IDs
foreach ($bookedDevices as $bookedDevice) {
    if ($bookedDevice->session_device_id && !in_array($bookedDevice->session_device_id, $sessionIds)) {
        $sessionIds[] = $bookedDevice->session_device_id;
    }
}
```

## Expected Result

### Before Fix:
```
10:18 - Device transferred to "individual"
09:55 - Order #123 created
```
❌ Missing: Session "test" creation activity

### After Fix:
```
10:18 - Device transferred from "test" to "individual"
09:55 - Order #123 created
10:16 - Session "test" created with device
```
✅ Complete history preserved

## Files Modified
- `app/Services/Timer/BookedDeviceService.php`
  - `transferBookedDeviceToSessionDevice()` - removed session deletion
  - `transferDeviceToGroup()` - removed session deletion

## Future Considerations
If database cleanup is needed in the future:
1. Create an archiving system for old sessions
2. Move old sessions to archive table
3. Keep activity log references intact
4. Run cleanup as a scheduled job (e.g., monthly)

For now, keeping all sessions provides the best user experience and data integrity.
