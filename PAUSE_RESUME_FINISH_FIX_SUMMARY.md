# Pause/Resume/Finish Activity Logging Fix

## Problem
When pause/resume/finish operations were called on BookedDevice:
- Multiple separate activities were created (BookedDevice update + BookedDevicePause)
- These should be grouped as parent-child under a single SessionDevice activity

## Solution Implemented

### 1. DeviceTimerService.php
Modified `pause()`, `resume()`, and `finish()` methods:

```php
public function pause(int $id)
{
    $bookedDevice = BookedDevice::findOrFail($id);
    
    // Validation...
    
    $oldStatus = $bookedDevice->status;
    
    // Create pause without logging
    $this->pauseService->createPause($id);
    
    // Update status without automatic logging
    $bookedDevice->withoutEvents(function() use ($bookedDevice) {
        $bookedDevice->update(['status' => BookedDeviceEnum::PAUSED->value]);
    });
    
    // Manual activity log for SessionDevice with BookedDevice as child
    $this->logSessionDeviceAction($bookedDevice, $oldStatus);
}
```

### 2. New Method: logSessionDeviceAction()
Creates a SessionDevice activity with BookedDevice as child in properties:

```php
private function logSessionDeviceAction(BookedDevice $bookedDevice, int $oldStatus)
{
    $sessionDevice = $bookedDevice->sessionDevice;
    
    if (!$sessionDevice) {
        return;
    }
    
    // Create activity log for SessionDevice with BookedDevice as child
    activity()
        ->useLog('SessionDevice')
        ->event('updated')
        ->performedOn($sessionDevice)
        ->withProperties([
            'attributes' => [
                'id' => $sessionDevice->id,
                'name' => $sessionDevice->name,
                'type' => $sessionDevice->type,
            ],
            'old' => [
                'name' => $sessionDevice->name,
                'type' => $sessionDevice->type,
            ],
            'children' => [[
                'id' => $bookedDevice->id,
                'event' => 'updated',
                'log_name' => 'BookedDevice',
                'device_id' => $bookedDevice->device_id,
                'device_type_id' => $bookedDevice->device_type_id,
                'device_time_id' => $bookedDevice->device_time_id,
                'status' => $bookedDevice->status,
                'old_status' => $oldStatus,
            ]],
        ])
        ->tap(function ($activity) use ($sessionDevice) {
            $activity->daily_id = $sessionDevice->daily_id;
        })
        ->log('SessionDevice action on BookedDevice');
}
```

### 3. BookedDevicePauseService.php
Modified to prevent automatic logging:

```php
public function createPause(int $id)
{
    $bookedDevice = BookedDevice::findOrFail($id);
    
    // Create pause without triggering events/logging
    $bookedDevice->pauses()->make([
        'paused_at' => Carbon::now('UTC'),
    ])->saveQuietly();
    
    return $bookedDevice;
}

public function resumePause(int $id)
{
    $bookedDevice = BookedDevice::findOrFail($id);
    $pause = $bookedDevice->pauses()->whereNull('resumed_at')->latest()->first();
    
    if ($pause) {
        // Update pause without triggering events/logging
        $pause->withoutEvents(function() use ($pause, $bookedDevice) {
            $pause->update([
                'resumed_at' => Carbon::now(),
                'duration_seconds' => $pause->paused_at->diffInSeconds(Carbon::now()),
            ]);
            
            $bookedDevice->increment('total_paused_seconds', $pause->duration_seconds);
            // ... rest of logic
        });
    }
    
    return $pause;
}
```

### 4. DailyActivityController.php
Updated to handle SessionDevice children from properties:

```php
} elseif ($modelName === 'sessiondevice') {
    $sessionId = $activity->subject_id;
    
    // Check if this SessionDevice uses children in properties (pause/resume/finish actions)
    $propertiesChildren = $activity->properties['children'] ?? [];
    
    if (!empty($propertiesChildren)) {
        // Children are in properties - convert to objects for resource processing
        $activity->children = collect($propertiesChildren)->map(function($childData) {
            return (object)[
                'log_name' => $childData['log_name'] ?? 'BookedDevice',
                'event' => $childData['event'] ?? 'updated',
                'subject_id' => $childData['id'] ?? null,
                'properties' => [
                    'attributes' => [
                        'device_id' => $childData['device_id'] ?? null,
                        'device_type_id' => $childData['device_type_id'] ?? null,
                        'device_time_id' => $childData['device_time_id'] ?? null,
                        'status' => $childData['status'] ?? null,
                    ],
                    'old' => [
                        'device_id' => $childData['device_id'] ?? null,
                        'device_type_id' => $childData['device_type_id'] ?? null,
                        'device_time_id' => $childData['device_time_id'] ?? null,
                        'status' => $childData['old_status'] ?? null,
                    ]
                ]
            ];
        })->all();
    } else {
        // Legacy system: look for separate BookedDevice activities
        $allChildren = $childrenMap['sessiondevice'][$sessionId] ?? [];
        $activity->children = collect($allChildren)->values()->all();
        
        foreach ($activity->children as $child) {
            $processedChildren[] = $child->id;
        }
    }
    
    $grouped->push($activity);
```

## Expected Output

### Pause Operation
```json
{
    "activityLogId": 28,
    "date": "26-Feb",
    "time": "01:37 AM",
    "eventType": "updated",
    "userName": "User Name",
    "model": {
        "modelName": "SessionDevice",
        "modelId": 22
    },
    "details": {},
    "children": [
        {
            "modelName": "BookedDevice",
            "eventType": "updated",
            "deviceName": {"old": "y45", "new": "y45"},
            "deviceType": {"old": "x", "new": "x"},
            "deviceTime": {"old": "f", "new": "f"},
            "status": {"old": 1, "new": 2}
        }
    ]
}
```

### Resume Operation
```json
{
    "activityLogId": 29,
    "date": "26-Feb",
    "time": "01:38 AM",
    "eventType": "updated",
    "userName": "User Name",
    "model": {
        "modelName": "SessionDevice",
        "modelId": 22
    },
    "details": {},
    "children": [
        {
            "modelName": "BookedDevice",
            "eventType": "updated",
            "deviceName": {"old": "y45", "new": "y45"},
            "deviceType": {"old": "x", "new": "x"},
            "deviceTime": {"old": "f", "new": "f"},
            "status": {"old": 2, "new": 1}
        }
    ]
}
```

### Finish Operation
```json
{
    "activityLogId": 30,
    "date": "26-Feb",
    "time": "01:39 AM",
    "eventType": "updated",
    "userName": "User Name",
    "model": {
        "modelName": "SessionDevice",
        "modelId": 22
    },
    "details": {},
    "children": [
        {
            "modelName": "BookedDevice",
            "eventType": "updated",
            "deviceName": {"old": "y45", "new": "y45"},
            "deviceType": {"old": "x", "new": "x"},
            "deviceTime": {"old": "f", "new": "f"},
            "status": {"old": 1, "new": 0}
        }
    ]
}
```

## Key Points

1. **Single Activity**: Only ONE SessionDevice activity is created per action
2. **No Separate Activities**: BookedDevicePause activities are NOT created separately
3. **Children in Properties**: BookedDevice appears as child in the SessionDevice activity
4. **Status Changes**: 
   - Pause: status 1 (ACTIVE) → 2 (PAUSED)
   - Resume: status 2 (PAUSED) → 1 (ACTIVE)
   - Finish: status 1 (ACTIVE) → 0 (FINISHED)
5. **Device Info**: All device information (name, type, time) is preserved

## Testing

Use the endpoints:
- `POST /api/v1/admin/device-timer/{id}/pause`
- `POST /api/v1/admin/device-timer/{id}/resume`
- `POST /api/v1/admin/device-timer/{id}/finish`

Then check activities via:
- `GET /api/v1/admin/daily/activities?dailyId={id}`

Verify:
- ✓ Only ONE activity per action
- ✓ Activity is SessionDevice with event='updated'
- ✓ BookedDevice appears as child
- ✓ Status change is correct
- ✓ No separate BookedDevicePause activity
