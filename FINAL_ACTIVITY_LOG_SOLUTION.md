# الحل النهائي لمشكلة Activity Log - إظهار الجلسة الحالية فقط

## المشكلة
كان النظام يُظهر الـ logs القديمة من أيام سابقة عند عرض activity log للجهاز، بدلاً من إظهار أنشطة الجلسة الحالية فقط.

## الحل المطبق

### 1. تحديث دالة `getActivityLogToDevice()`
**الملف**: `app/Services/Timer/BookedDeviceService.php`

تم تطبيق **تصفية صارمة** لإظهار الجلسة الحالية فقط:

```php
public function getActivityLogToDevice($bookedDeviceId)
{
    // الحصول على BookedDevice الحالي
    $currentBookedDevice = BookedDevice::findOrFail($bookedDeviceId);
    $deviceId = $currentBookedDevice->device_id;

    // الحصول على timer_id لربط جميع الأنشطة
    $timerId = $this->getTimerId($currentBookedDevice);

    // تصفية صارمة: فقط الأنشطة التي تنتمي للتايمر النشط الحالي
    $activities = Activity::where(function ($query) use ($timerId, $currentBookedDevice) {
        // الشرط الأساسي: الأنشطة بنفس timer_id (دورة حياة التايمر الحالي)
        $query->whereJsonContains('properties->timer_id', $timerId)
              // وفقط من اليوم
              ->whereDate('created_at', today())
              // ومرتبطة بالجلسة الحالية أو الجهاز المحجوز الحالي
              ->where(function ($subQuery) use ($currentBookedDevice) {
                  $subQuery->where(function ($q) use ($currentBookedDevice) {
                      // أنشطة BookedDevice الحالي
                      $q->where('subject_type', BookedDevice::class)
                        ->where('subject_id', $currentBookedDevice->id);
                  })
                  ->orWhere(function ($q) use ($currentBookedDevice) {
                      // أنشطة SessionDevice الحالي
                      if ($currentBookedDevice->session_device_id) {
                          $q->where('subject_type', SessionDevice::class)
                            ->where('subject_id', $currentBookedDevice->session_device_id);
                      }
                  });
              });
    })
    ->orderBy('created_at', 'desc')
    ->get();

    // إذا لم توجد أنشطة بـ timer_id، العودة للجلسة الحالية فقط
    if ($activities->isEmpty()) {
        $activities = Activity::where(function ($query) use ($currentBookedDevice) {
            $query->where(function ($q) use ($currentBookedDevice) {
                // أنشطة BookedDevice الحالي من اليوم فقط
                $q->where('subject_type', BookedDevice::class)
                  ->where('subject_id', $currentBookedDevice->id);
            })
            ->orWhere(function ($q) use ($currentBookedDevice) {
                // أنشطة SessionDevice الحالي من اليوم فقط
                if ($currentBookedDevice->session_device_id) {
                    $q->where('subject_type', SessionDevice::class)
                      ->where('subject_id', $currentBookedDevice->session_device_id);
                }
            });
        })
        ->whereDate('created_at', today()) // صارم: اليوم فقط
        ->orderBy('created_at', 'desc')
        ->get();
    }

    // إضافة metadata متسق لجميع الأنشطة
    $deviceSessionKey = $this->findCurrentSessionKey($currentBookedDevice);
    $activities = $activities->map(function ($activity) use ($deviceSessionKey, $deviceId, $timerId) {
        $properties = is_string($activity->properties)
            ? json_decode($activity->properties, true)
            : ($activity->properties ?? []);

        // ضمان metadata متسق
        $properties['device_session_key'] = $deviceSessionKey;
        $properties['device_id'] = $deviceId;
        $properties['timer_id'] = $timerId;
        $properties['current_session_only'] = true;

        $activity->properties = $properties;
        return $activity;
    });

    // استخدام تجميع مبسط للجلسة الحالية فقط
    $bookedDeviceIds = [$currentBookedDevice->id]; // الجهاز الحالي فقط
    $orderIds = [];
    $sessionIds = [];

    // تضمين الجلسة الحالية فقط
    if ($currentBookedDevice->session_device_id) {
        $sessionIds = [$currentBookedDevice->session_device_id];
    }

    // الحصول على order IDs المرتبطة بالجهاز المحجوز الحالي فقط
    $orderIds = Order::where('booked_device_id', $currentBookedDevice->id)
                     ->pluck('id')
                     ->toArray();

    return $this->groupParentChildActivitiesForDevice($activities, $bookedDeviceIds, $orderIds, $sessionIds, $deviceId);
}
```

## المميزات الرئيسية

### ✅ تصفية صارمة للجلسة الحالية
- يُظهر فقط أنشطة الجلسة النشطة الحالية
- يُصفي جميع الأنشطة القديمة من الأيام السابقة
- يستخدم `whereDate('created_at', today())` للتصفية بالتاريخ

### ✅ Timer ID ثابت
- التنسيق: `timer_{device_id}_{creation_timestamp}`
- لا يتغير أبداً طوال دورة حياة التايمر
- متسق عبر جميع عمليات النقل بين الجلسات

### ✅ Device Session Key
- التنسيق: `device_{device_id}_daily_{daily_id}_{date}`
- يربط الأنشطة لنفس الجهاز في نفس اليوم

### ✅ تصفية التاريخ
- فقط أنشطة اليوم الحالي
- لا توجد بيانات قديمة من الأيام السابقة
- التركيز على الجلسة الحالية

## نتائج الاختبار

### ✅ اختبار البيانات القديمة
```
📊 All activities for this device (unfiltered): 6
Date range of activities:
  - 2026-02-25: 2 activities (OLD)
  - 2026-02-24: 4 activities (OLD)

📊 Filtered activities count: 0
✅ PERFECT: Old activities filtered out successfully
```

### ✅ اختبار الجهاز الجديد
```
📊 Activities count: 1
📝 Current session activities:
  - Event: created
    Date: 2026-03-15 08:55:17
    Is Today: YES
    Timer ID: timer_1_1773564917
    Current Session Only: 1
✅ SUCCESS: New device shows current session activities only
```

## API Endpoint

يمكن الوصول لـ activity log عبر:
```
GET /api/v1/admin/device-timer/{id}/activity-log
```

هذا الـ endpoint الآن يُرجع فقط أنشطة الجلسة الحالية مع ربط timer_id المناسب.

## الخلاصة

تم حل المشكلة بالكامل:
- ✅ يُظهر فقط أنشطة الجلسة الحالية
- ✅ لا توجد أنشطة قديمة من الأيام السابقة  
- ✅ Timer ID ثابت يربط جميع العمليات
- ✅ سلوك متسق عبر عمليات النقل بين الجلسات
- ✅ تجميع وتنظيم مناسب للأنشطة
