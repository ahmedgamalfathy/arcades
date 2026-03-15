# حل مشكلة عدم ظهور عمليات Pause/Resume في Activity Log

## المشكلة
المستخدم قام بعمل pause و resume للجهاز أكثر من مرة ولكن هذه العمليات لا تظهر في activity log.

## السبب
كانت دالة `logSessionDeviceAction` في `DeviceTimerService` لا تحتوي على `timer_id` و `device_session_key` المطلوبة للتصفية الجديدة في `getActivityLogToDevice`.

## الحل المطبق

### 1. تحديث دالة `logSessionDeviceAction`
**الملف**: `app/Services/Timer/DeviceTimerService.php`

```php
private function logSessionDeviceAction(BookedDevice $bookedDevice, int $oldStatus)
{
    $sessionDevice = $bookedDevice->sessionDevice;

    if (!$sessionDevice) {
        return;
    }

    // Generate consistent keys for activity tracking
    $dailyId = $sessionDevice->daily_id;
    $deviceStartDate = $bookedDevice->created_at->format('Y-m-d');
    $deviceSessionKey = 'device_' . $bookedDevice->device_id . '_daily_' . $dailyId . '_' . $deviceStartDate;
    $timerId = 'timer_' . $bookedDevice->device_id . '_' . $bookedDevice->created_at->timestamp;

    // Determine action type based on status change
    $actionType = '';
    if ($oldStatus == BookedDeviceEnum::ACTIVE->value && $bookedDevice->status == BookedDeviceEnum::PAUSED->value) {
        $actionType = 'pause';
    } elseif ($oldStatus == BookedDeviceEnum::PAUSED->value && $bookedDevice->status == BookedDeviceEnum::ACTIVE->value) {
        $actionType = 'resume';
    } elseif ($bookedDevice->status == BookedDeviceEnum::FINISHED->value) {
        $actionType = 'finish';
    } else {
        $actionType = 'update';
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
            'device_session_key' => $deviceSessionKey, // Add persistent device key
            'timer_id' => $timerId, // Add timer lifecycle ID
            'action_type' => $actionType, // Add action type for clarity
            'session_type' => $sessionDevice->type == 0 ? 'individual' : 'group' // Add session type
        ])
        ->tap(function ($activity) use ($sessionDevice) {
            $activity->daily_id = $sessionDevice->daily_id;
        })
        ->log("SessionDevice - Device {$actionType}");
}
```

### 2. تحديث دالة `changeDeviceTime`
أيضاً تم تحديث دالة تغيير وقت الجهاز لتتضمن نفس المفاتيح المطلوبة.

## المميزات الجديدة

### ✅ إضافة المفاتيح المطلوبة
- **timer_id**: `timer_{device_id}_{creation_timestamp}`
- **device_session_key**: `device_{device_id}_daily_{daily_id}_{date}`

### ✅ تحديد نوع العملية
- **action_type**: يحدد نوع العملية (pause, resume, finish, update)
- **session_type**: يحدد نوع الجلسة (individual, group)

### ✅ تتبع تغيير الحالة
- **status**: الحالة الجديدة
- **old_status**: الحالة القديمة
- **Status Change**: عرض واضح للتغيير (1 → 2 للـ pause, 2 → 1 للـ resume)

## نتائج الاختبار

```
📊 Activities count: 5

📝 Activities with pause/resume operations:
  - Event: created
    Description: SessionDevice - Individual time created
    Timer ID: timer_1_1773565413
    
  - Event: updated
    Description: SessionDevice - Device pause
    Timer ID: timer_1_1773565413
    Action Type: pause
    Status Change: 1 → 2
    
  - Event: updated
    Description: SessionDevice - Device resume
    Timer ID: timer_1_1773565413
    Action Type: resume
    Status Change: 2 → 1
    
  - Event: updated
    Description: SessionDevice - Device pause
    Timer ID: timer_1_1773565413
    Action Type: pause
    Status Change: 1 → 2
    
  - Event: updated
    Description: SessionDevice - Device resume
    Timer ID: timer_1_1773565413
    Action Type: resume
    Status Change: 2 → 1

🎯 Operation Summary:
  - Pause operations: 2
  - Resume operations: 2
✅ SUCCESS: All pause/resume operations are logged and visible
```

## العمليات المشمولة

الآن جميع العمليات التالية تظهر في activity log:
- ✅ **Pause**: إيقاف مؤقت للجهاز
- ✅ **Resume**: استئناف تشغيل الجهاز
- ✅ **Finish**: إنهاء الجهاز
- ✅ **Change Time**: تغيير نوع الوقت
- ✅ **Transfer**: نقل بين الجلسات

## الخلاصة

تم حل المشكلة بالكامل. الآن عندما يقوم المستخدم بعمل pause و resume للجهاز أكثر من مرة، ستظهر جميع هذه العمليات في activity log مع:
- Timer ID ثابت يربط جميع العمليات
- تفاصيل واضحة عن نوع العملية
- تتبع تغيير الحالة
- تصفية صحيحة للجلسة الحالية فقط
