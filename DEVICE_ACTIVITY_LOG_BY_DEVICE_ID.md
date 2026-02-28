# Device Activity Log - Track by Device ID

## المشكلة

عندما نغير نوع الوقت للجهاز عبر `/change-time`:
- النظام يقفل الـ `BookedDevice` القديم (finish)
- ويفتح `BookedDevice` جديد بنوع وقت مختلف
- لكن كلاهما لنفس الجهاز الفعلي (`device_id`)

الـ API القديم كان:
```
GET /api/v1/admin/device-timer/{bookedDeviceId}/activity-log
```

المشكلة:
- يأخذ `bookedDeviceId` (معرف الوقت)
- يعرض فقط أنشطة هذا الوقت المحدد
- لا يعرض الأنشطة السابقة عندما كان الجهاز بنوع وقت مختلف

## الحل

تم تغيير الـ API ليأخذ `device_id` بدلاً من `bookedDeviceId`:

```
GET /api/v1/admin/device-timer/{deviceId}/activity-log
```

الآن:
- يأخذ `device_id` (معرف الجهاز الفعلي مثل "بلايستيشن 3")
- يبحث عن كل الـ `BookedDevice` records لهذا الجهاز
- يجمع كل الأنشطة المرتبطة بكل هذه الـ records
- يعرض تاريخ كامل للجهاز عبر كل أنواع الأوقات

## مثال

### السيناريو:
1. جهاز "بلايستيشن 3" (`device_id = 5`)
2. بدأ بوقت "ساعة" (`bookedDevice_id = 10`)
3. تم تغيير الوقت إلى "نصف ساعة" (`bookedDevice_id = 15`)
4. تم تغيير الوقت إلى "فردي" (`bookedDevice_id = 20`)

### API القديم:
```
GET /device-timer/20/activity-log
```
النتيجة: فقط أنشطة الوقت "فردي" ❌

### API الجديد:
```
GET /device-timer/5/activity-log
```
النتيجة: كل الأنشطة للجهاز عبر كل الأوقات ✅
- أنشطة الوقت "ساعة"
- أنشطة الوقت "نصف ساعة"  
- أنشطة الوقت "فردي"
- كل الطلبات (Orders) المرتبطة
- كل الإيقافات (Pauses)
- كل التحويلات (Transfers)

## التغييرات التقنية

### 1. BookedDeviceService.php

#### getActivityLogToDevice()
```php
// قبل
public function getActivityLogToDevice($id)
{
    $bookedDevice = BookedDevice::findOrFail($id);
    // يجلب أنشطة هذا الـ BookedDevice فقط
}

// بعد
public function getActivityLogToDevice($deviceId)
{
    // يجلب كل الـ BookedDevice records لهذا الجهاز
    $bookedDevices = BookedDevice::where('device_id', $deviceId)
        ->with(['orders', 'sessionDevice', 'pauses'])
        ->get();
    
    // يجمع كل الـ IDs المرتبطة
    $bookedDeviceIds = $bookedDevices->pluck('id')->toArray();
    $orderIds = [...];
    $sessionIds = [...];
    $pauseIds = [...];
    
    // يجلب كل الأنشطة
    $activities = Activity::where(function ($query) use (...) {
        // BookedDevice activities
        // Order activities
        // SessionDevice activities
        // Pause activities
    })->get();
}
```

#### groupParentChildActivitiesForDevice()
```php
// قبل
private function groupParentChildActivitiesForDevice($activities, $bookedDeviceId, ...)
{
    // يتحقق من bookedDeviceId واحد
    if ($child->subject_id != $bookedDeviceId) {
        return false;
    }
}

// بعد
private function groupParentChildActivitiesForDevice($activities, $bookedDeviceIds, ...)
{
    // يتحقق من array من bookedDeviceIds
    if (!in_array($child->subject_id, $bookedDeviceIds)) {
        return false;
    }
}
```

### 2. DeviceTimerController.php

```php
// قبل
public function getActitvityLogToDevice($id)
{
    $groupedActivities = $this->bookedDeviceService->getActivityLogToDevice($id);
}

// بعد
public function getActitvityLogToDevice($deviceId)
{
    $groupedActivities = $this->bookedDeviceService->getActivityLogToDevice($deviceId);
}
```

## الاستخدام

### الحصول على device_id

من جدول `booked_devices`:
```sql
SELECT device_id FROM booked_devices WHERE id = {bookedDeviceId};
```

أو من الـ API:
```
GET /device-timer/{bookedDeviceId}/show
```
الرد يحتوي على `device_id`

### استدعاء الـ API

```
GET /api/v1/admin/device-timer/{device_id}/activity-log
```

مثال:
```
GET /api/v1/admin/device-timer/5/activity-log
```

### الرد

```json
[
  {
    "activityLogId": 100,
    "date": "28-Feb",
    "time": "10:00",
    "eventType": "created",
    "model": {"modelName": "SessionDevice", "modelId": 10},
    "details": {...},
    "children": [...]
  },
  {
    "activityLogId": 105,
    "date": "28-Feb",
    "time": "11:00",
    "eventType": "updated",
    "model": {"modelName": "SessionDevice", "modelId": 10},
    "details": {"deviceTime": {"old": "ساعة", "new": "نصف ساعة"}},
    "children": [...]
  },
  {
    "activityLogId": 110,
    "date": "28-Feb",
    "time": "12:00",
    "eventType": "updated",
    "model": {"modelName": "SessionDevice", "modelId": 15},
    "details": {"deviceTime": {"old": "نصف ساعة", "new": "فردي"}},
    "children": [...]
  }
]
```

## الفوائد

1. ✅ تتبع كامل لتاريخ الجهاز
2. ✅ رؤية كل تغييرات نوع الوقت
3. ✅ رؤية كل الطلبات والإيقافات عبر كل الأوقات
4. ✅ تقارير أفضل وأكثر دقة
5. ✅ تجربة مستخدم أفضل

## الملفات المعدلة

- `app/Services/Timer/BookedDeviceService.php`
  - `getActivityLogToDevice()` method
  - `groupParentChildActivitiesForDevice()` method
  
- `app/Http/Controllers/API/V1/Dashboard/Timer/DeviceTimerController.php`
  - `getActitvityLogToDevice()` method

## ملاحظات مهمة

1. الـ Route لم يتغير: `Route::get('{id}/activity-log','getActitvityLogToDevice')`
2. لكن الآن `{id}` يمثل `device_id` وليس `bookedDevice_id`
3. إذا لم يوجد أي `BookedDevice` لهذا `device_id`، يرجع خطأ 404
4. الأنشطة مرتبة من الأحدث للأقدم
5. يتعامل مع الـ soft deleted sessions بشكل صحيح
