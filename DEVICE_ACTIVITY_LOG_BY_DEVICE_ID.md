# Device Activity Log - Track by BookedDevice ID (Session-Based)

## المشكلة

عندما نغير نوع الوقت للجهاز عبر `/change-time`:
- النظام يقفل الـ `BookedDevice` القديم (finish)
- ويفتح `BookedDevice` جديد بنوع وقت مختلف
- لكن كلاهما لنفس الجهاز الفعلي (`device_id`)
- وكلاهما في نفس الجلسة (`session_device_id`)

**سيناريو إضافي:**
- بعد إنهاء الجلسة، قد نفتح نفس الجهاز مرة أخرى في جلسة جديدة
- هذه جلسة مختلفة تماماً ولا يجب أن تظهر مع الجلسة القديمة

## الحل

الـ API يأخذ `bookedDeviceId`، ثم:
1. يجلب الـ `BookedDevice` المطلوب
2. يستخرج `device_id` و `session_device_id` منه
3. يبحث عن كل الـ `BookedDevice` records لنفس `device_id` **ونفس `session_device_id`**
4. يجمع كل الأنشطة المرتبطة بهذه الجلسة فقط

## التمييز الذكي

### مثال توضيحي:

**الجلسة الأولى:** (اليوم الساعة 10 صباحاً)
- جهاز "بلايستيشن 3" (`device_id = 5`)
- جلسة فردية (`session_device_id = 20`)
- بدأ بوقت "ساعة" (`bookedDevice_id = 41`)
- غيرنا لـ "نصف ساعة" (`bookedDevice_id = 42`)
- غيرنا لـ "فردي" (`bookedDevice_id = 43`)
- أنهينا الجلسة (finish)

**الجلسة الثانية:** (نفس اليوم الساعة 3 مساءً)
- نفس الجهاز "بلايستيشن 3" (`device_id = 5`)
- جلسة فردية جديدة (`session_device_id = 25`)
- بدأ بوقت "ساعة" (`bookedDevice_id = 50`)
- غيرنا لـ "نصف ساعة" (`bookedDevice_id = 51`)

### النتائج:

```
GET /device-timer/41/activity-log
GET /device-timer/42/activity-log
GET /device-timer/43/activity-log
```
كلهم يرجعون: أنشطة الجلسة الأولى فقط (41, 42, 43) ✅

```
GET /device-timer/50/activity-log
GET /device-timer/51/activity-log
```
كلهم يرجعون: أنشطة الجلسة الثانية فقط (50, 51) ✅

## الكود

```php
public function getActivityLogToDevice($bookedDeviceId)
{
    // 1. جلب الـ BookedDevice المطلوب
    $currentBookedDevice = BookedDevice::findOrFail($bookedDeviceId);
    $deviceId = $currentBookedDevice->device_id;
    $sessionDeviceId = $currentBookedDevice->session_device_id;

    // 2. جلب كل BookedDevice records لنفس الجهاز ونفس الجلسة
    $bookedDevices = BookedDevice::where('device_id', $deviceId)
        ->where('session_device_id', $sessionDeviceId)  // ✅ هنا التمييز!
        ->get();
    
    // 3. جلب كل الأنشطة لهذه الجلسة فقط
}
```

## جدول التمييز

| bookedDeviceId | device_id | session_device_id | الجلسة | النتيجة |
|---------------|-----------|-------------------|--------|---------|
| 41 | 5 | 20 | الأولى | 41, 42, 43 |
| 42 | 5 | 20 | الأولى | 41, 42, 43 |
| 43 | 5 | 20 | الأولى | 41, 42, 43 |
| 50 | 5 | 25 | الثانية | 50, 51 |
| 51 | 5 | 25 | الثانية | 50, 51 |

## الاستخدام

```
GET /api/v1/admin/device-timer/{bookedDeviceId}/activity-log
```

### النتيجة

```json
[
  {
    "activityLogId": 100,
    "date": "28-Feb",
    "time": "10:00",
    "eventType": "created",
    "model": {"modelName": "SessionDevice", "modelId": 20},
    "details": {
      "name": {"old": "", "new": "individual"},
      "type": {"old": "", "new": 0}
    },
    "children": [{
      "modelName": "BookedDevice",
      "eventType": "created",
      "deviceName": {"old": "", "new": "بلايستيشن 3"},
      "deviceType": {"old": "", "new": "بلايستيشن"},
      "deviceTime": {"old": "", "new": "ساعة"},
      "status": {"old": "", "new": 1}
    }]
  },
  {
    "activityLogId": 105,
    "date": "28-Feb",
    "time": "11:00",
    "eventType": "updated",
    "model": {"modelName": "SessionDevice", "modelId": 20},
    "details": {
      "name": {"old": "individual", "new": "individual"},
      "type": {"old": 0, "new": 0}
    },
    "children": [{
      "modelName": "BookedDevice",
      "eventType": "updated",
      "deviceTime": {"old": "ساعة", "new": "نصف ساعة"}
    }]
  },
  {
    "activityLogId": 110,
    "date": "28-Feb",
    "time": "12:00",
    "eventType": "updated",
    "model": {"modelName": "SessionDevice", "modelId": 20},
    "details": {
      "name": {"old": "individual", "new": "individual"},
      "type": {"old": 0, "new": 0}
    },
    "children": [{
      "modelName": "BookedDevice",
      "eventType": "updated",
      "deviceTime": {"old": "نصف ساعة", "new": "فردي"}
    }]
  }
]
```

## الفوائد

1. ✅ الـ API يأخذ `bookedDeviceId` كما كان (لا تغيير في الاستخدام)
2. ✅ تتبع كامل لتاريخ الجلسة الواحدة
3. ✅ لو غيرنا نوع الوقت 10 مرات في نفس الجلسة، كل الأنشطة تظهر
4. ✅ الجلسات المختلفة لنفس الجهاز لا تختلط
5. ✅ تمييز واضح بين الجلسات المختلفة
6. ✅ يؤثر فقط على اللوج - لا يؤثر على أي شيء آخر

## الملفات المعدلة

- `app/Services/Timer/BookedDeviceService.php`
  - `getActivityLogToDevice()` method
  
- `app/Http/Controllers/API/V1/Dashboard/Timer/DeviceTimerController.php`
  - `getActitvityLogToDevice()` method

## ملاحظات مهمة

1. الـ Route لم يتغير: `Route::get('{id}/activity-log','getActitvityLogToDevice')`
2. الـ `{id}` يمثل `bookedDevice_id` (كما كان)
3. يجلب كل الأنشطة للجلسة الواحدة فقط
4. الجلسات المختلفة لنفس الجهاز تبقى منفصلة
5. إذا لم يوجد الـ `bookedDevice_id`، يرجع خطأ 404
6. الأنشطة مرتبة من الأحدث للأقدم
7. التغيير يؤثر فقط على اللوج - لا يؤثر على أي شيء آخر
8. `session_device_id` هو المعرّف الذي يربط الأجهزة في نفس الجلسة
