# Individual Time Activity Log Fix

## Problem
When creating an individual time session, the activity log was showing TWO separate activities instead of ONE SessionDevice activity with BookedDevice as a child.

## Solution
Modified both `individualTime()` and `groupTime()` to:
1. Create SessionDevice without automatic logging
2. Create BookedDevice without automatic logging  
3. Create ONE manual activity log with correct format:
   - **Created event**: `old: {name: "", type: ""}`
   - **Updated event**: `old: {name: value, type: value}`

## Expected Result

### Creating Individual Time
```json
{
  "activityLogId": 153,
  "date": "26-Feb",
  "time": "07:59",
  "eventType": "created",
  "userName": "admin",
  "model": {"modelName": "SessionDevice", "modelId": 16},
  "details": {
    "name": {"old": "", "new": "individual"},
    "type": {"old": "", "new": 0}
  },
  "children": [{
    "modelName": "BookedDevice",
    "eventType": "created",
    "deviceName": {"old": "", "new": "جهاز رقم 3"},
    "deviceType": {"old": "", "new": "بلايستيشن 5"},
    "deviceTime": {"old": "", "new": "فردي"},
    "status": {"old": "", "new": 1}
  }]
}
```

### Creating New Group Time
```json
{
  "eventType": "created",
  "details": {
    "name": {"old": "", "new": "مجموعة 1"},
    "type": {"old": "", "new": 1}
  },
  "children": [...]
}
```

### Adding Device to Existing Group
```json
{
  "eventType": "updated",
  "details": {
    "name": {"old": "مجموعة 1", "new": "مجموعة 1"},
    "type": {"old": 1, "new": 1}
  },
  "children": [...]
}
```

## Files Modified
- `app/Http/Controllers/API/V1/Dashboard/Timer/DeviceTimerController.php`
  - `individualTime()` method
  - `groupTime()` method
