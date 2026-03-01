# إصلاح فلترة الأجهزة في الوقت المجمع

## المشكلة

في **الوقت المجمع** (group session)، عندما يكون عندنا جهازين أو أكثر في نفس الجلسة:

**مثال:**
- جهاز رقم 1 "بلايستيشن 3" (`device_id = 5`, `session_device_id = 20`)
- جهاز رقم 2 "بلايستيشن 5" (`device_id = 8`, `session_device_id = 20`)

**المشكلة:**
عند طلب activity log لجهاز رقم 1، كان يظهر أنشطة الجهازين معاً!

**السبب:**
- الكود كان يجلب كل الأنشطة للـ `session_device_id`
- لكن لم يكن يفلتر الـ children حسب `device_id`
- فكانت أنشطة كل الأجهزة في الجلسة تظهر

## الحل

تم إضافة فلترة إضافية في `groupParentChildActivitiesForDevice`:

### 1. تمرير `device_id` للدالة

```php
public function getActivityLogToDevice($bookedDeviceId)
{
    $currentBookedDevice = BookedDevice::findOrFail($bookedDeviceId);
    $deviceId = $currentBookedDevice->device_id;
    $sessionDeviceId = $currentBookedDevice->session_device_id;
    
    // ...
    
    // Pass deviceId to filter children by device
    return $this->groupParentChildActivitiesForDevice(
        $activities, 
        $bookedDeviceIds, 
        $orderIds, 
        $sessionIds, 
        $deviceId  // ✅ تمرير device_id
    );
}
```

### 2. تحديث signature الدالة

```php
private function groupParentChildActivitiesForDevice(
    $activities, 
    $bookedDeviceIds, 
    $orderIds, 
    $sessionIds, 
    $deviceId = null  // ✅ معامل جديد
)
```

### 3. فلترة الـ children حسب device_id

```php
if (!empty($propertiesChildren)) {
    $activity->children = collect($propertiesChildren)
        // ✅ فلترة حسب device_id
        ->filter(function($childData) use ($deviceId, $bookedDeviceIds) {
            if ($deviceId !== null) {
                $childDeviceId = $childData['device_id'] ?? null;
                $childId = $childData['id'] ?? null;
                
                // نعرض فقط الـ children الخاصة بهذا الجهاز
                return ($childDeviceId == $deviceId) || 
                       in_array($childId, $bookedDeviceIds);
            }
            return true;
        })
        ->map(function($childData) {
            // ... تحويل البيانات
        })
        ->values()
        ->all();
}
```

## النتيجة

### قبل الإصلاح:

```
GET /device-timer/41/activity-log  (جهاز 1)
```

النتيجة:
```json
{
  "model": {"modelName": "SessionDevice", "modelId": 20},
  "children": [
    {"deviceName": "بلايستيشن 3"},  // ✅ جهاز 1
    {"deviceName": "بلايستيشن 5"}   // ❌ جهاز 2 (خطأ!)
  ]
}
```

### بعد الإصلاح:

```
GET /device-timer/41/activity-log  (جهاز 1)
```

النتيجة:
```json
{
  "model": {"modelName": "SessionDevice", "modelId": 20},
  "children": [
    {"deviceName": "بلايستيشن 3"}  // ✅ جهاز 1 فقط
  ]
}
```

```
GET /device-timer/50/activity-log  (جهاز 2)
```

النتيجة:
```json
{
  "model": {"modelName": "SessionDevice", "modelId": 20},
  "children": [
    {"deviceName": "بلايستيشن 5"}  // ✅ جهاز 2 فقط
  ]
}
```

## السيناريوهات المدعومة

### 1. وقت فردي (Individual)
- جهاز واحد في جلسة واحدة
- يعمل كما كان ✅

### 2. وقت مجمع (Group)
- عدة أجهزة في جلسة واحدة
- كل جهاز يعرض أنشطته فقط ✅

### 3. تغيير نوع الوقت
- نفس الجهاز، نفس الجلسة، أنواع أوقات مختلفة
- يعرض كل الأنشطة للجهاز في هذه الجلسة ✅

### 4. جلسات متعددة لنفس الجهاز
- نفس الجهاز، جلسات مختلفة
- كل جلسة منفصلة ✅

## الملفات المعدلة

- `app/Services/Timer/BookedDeviceService.php`
  - `getActivityLogToDevice()` - تمرير `device_id`
  - `groupParentChildActivitiesForDevice()` - إضافة فلترة بـ `device_id`

## الفوائد

1. ✅ فصل واضح بين أنشطة الأجهزة المختلفة في الوقت المجمع
2. ✅ كل جهاز يعرض أنشطته فقط
3. ✅ لا تداخل بين الأجهزة
4. ✅ يعمل مع كل السيناريوهات (فردي، مجمع، تغيير وقت، جلسات متعددة)
5. ✅ الحل متوافق مع الكود الموجود (backward compatible)

## ملاحظات

- الفلترة تحدث فقط عند وجود `device_id` (للأمان)
- إذا لم يتم تمرير `device_id`، يعمل الكود كما كان
- الفلترة تعمل على الـ children من properties فقط
- Legacy system (الأنشطة المنفصلة) تستخدم `bookedDeviceIds` للفلترة
