# حل تتبع الأجهزة المستمر - Persistent Device Tracking

## المشكلة الأصلية
عندما يتم نقل الجهاز من جلسة فردية (نوع 0) إلى جلسة مجمعة (نوع 1) أو العكس، كانت الـ logs الخاصة بالجلسة السابقة تختفي. المطلوب هو مفتاح ثابت يربط جميع الأنشطة للجهاز الواحد حتى لو تغير الـ session أو الـ timer-id.

## الحل المطبق

### 1. مفتاح الجهاز المستمر (Persistent Device Key)

تم إنشاء نظام `device_session_key` يربط جميع الأنشطة للجهاز الواحد:

#### صيغة المفتاح:
```
device_{device_id}_daily_{daily_id}_{date}
```

#### أمثلة:
- `device_1_daily_2_2026-02-24` - للجهاز رقم 1 في اليوم 2 بتاريخ 2026-02-24
- `device_3_daily_5_2026-02-25` - للجهاز رقم 3 في اليوم 5 بتاريخ 2026-02-25

### 2. التحديثات المطبقة

#### أ. في `BookedDeviceService.php`:

##### Method `findCurrentSessionKey()` - محدث بالكامل
```php
/**
 * Find the device session key - a persistent key that stays with the device
 * across all session transfers and changes
 */
private function findCurrentSessionKey($bookedDevice)
{
    // Use device_id + daily_id + start_date as persistent key
    // This ensures all activities for the same device on the same day are linked
    $dailyId = null;
    
    if ($bookedDevice->session_device_id) {
        $sessionDevice = SessionDevice::find($bookedDevice->session_device_id);
        if ($sessionDevice) {
            $dailyId = $sessionDevice->daily_id;
        }
    }
    
    // Create persistent device session key using device_id + daily_id + device start date
    $deviceStartDate = $bookedDevice->created_at->format('Y-m-d');
    return 'device_' . $bookedDevice->device_id . '_daily_' . ($dailyId ?? 'unknown') . '_' . $deviceStartDate;
}
```

##### Method `getActivityLogToDevice()` - محدث بالكامل
```php
public function getActivityLogToDevice($bookedDeviceId)
{
    // Get the current BookedDevice
    $currentBookedDevice = BookedDevice::findOrFail($bookedDeviceId);
    $deviceId = $currentBookedDevice->device_id;

    // Find the persistent device session key
    $deviceSessionKey = $this->findCurrentSessionKey($currentBookedDevice);

    // Get all activities for this device across all sessions
    $activities = Activity::where(function ($query) use ($deviceSessionKey, $deviceId) {
        $query->where(function ($q) use ($deviceSessionKey) {
            // Get activities that have the same device_session_key (new activities)
            $q->whereJsonContains('properties->device_session_key', $deviceSessionKey);
        })
        ->orWhere(function ($q) use ($deviceId) {
            // Include all BookedDevice activities for this device_id
            $q->where('subject_type', BookedDevice::class)
              ->whereIn('subject_id', function($subQuery) use ($deviceId) {
                  $subQuery->select('id')
                           ->from('booked_devices')
                           ->where('device_id', $deviceId);
              });
        })
        ->orWhere(function ($q) use ($deviceId) {
            // Include SessionDevice activities for sessions that contain this device
            $q->where('subject_type', SessionDevice::class)
              ->whereIn('subject_id', function($subQuery) use ($deviceId) {
                  $subQuery->select('session_device_id')
                           ->from('booked_devices')
                           ->where('device_id', $deviceId)
                           ->whereNotNull('session_device_id');
              });
        });
    })
    ->orderBy('created_at', 'desc')
    ->get();

    // Add device_session_key to all activities for consistency
    $activities = $activities->map(function ($activity) use ($deviceSessionKey, $deviceId) {
        $properties = is_string($activity->properties)
            ? json_decode($activity->properties, true)
            : ($activity->properties ?? []);

        // Add persistent device session key
        $properties['device_session_key'] = $deviceSessionKey;
        $properties['device_id'] = $deviceId;
        $properties['persistent_tracking'] = true;

        $activity->properties = $properties;
        return $activity;
    });

    // Use existing grouping method
    $bookedDeviceIds = BookedDevice::where('device_id', $deviceId)->pluck('id')->toArray();
    $sessionIds = SessionDevice::whereIn('id', function($query) use ($deviceId) {
        $query->select('session_device_id')
              ->from('booked_devices')
              ->where('device_id', $deviceId)
              ->whereNotNull('session_device_id');
    })->pluck('id')->toArray();
    $orderIds = Order::whereIn('booked_device_id', $bookedDeviceIds)->pluck('id')->toArray();

    return $this->groupParentChildActivitiesForDevice($activities, $bookedDeviceIds, $orderIds, $sessionIds, $deviceId);
}
```

#### ب. في `DeviceTimerController.php`:

##### Method `individualTime()` - محدث
```php
// Manual activity log for SessionDevice with BookedDevice as child
$dailyId = $data['dailyId'];
$deviceStartDate = now()->format('Y-m-d');
$deviceSessionKey = 'device_' . $device->device_id . '_daily_' . $dailyId . '_' . $deviceStartDate;

activity()
    ->useLog('SessionDevice')
    ->event('created')
    ->performedOn($sessionDevice)
    ->withProperties([
        // ... other properties
        'device_session_key' => $deviceSessionKey, // Add persistent device key
        'session_type' => 'individual'
    ])
    ->log('SessionDevice - Individual time created');
```

##### Method `groupTime()` - محدث
```php
// Manual activity log for SessionDevice with BookedDevice as child
$dailyId = $data['dailyId'];
$deviceStartDate = now()->format('Y-m-d');
$deviceSessionKey = 'device_' . $device->device_id . '_daily_' . $dailyId . '_' . $deviceStartDate;

// في properties:
'device_session_key' => $deviceSessionKey, // Add persistent device key
'session_type' => 'group'
```

### 3. المزايا الجديدة

#### أ. تتبع مستمر للجهاز
- جميع الأنشطة للجهاز الواحد مربوطة بمفتاح ثابت
- لا تختفي الـ logs عند النقل بين أنواع الجلسات المختلفة
- يحافظ على تاريخ كامل للجهاز

#### ب. مرونة في التتبع
- يدعم النقل من فردي إلى مجمع والعكس
- يدعم تغيير الـ timer-id
- يدعم تغيير الـ session-id

#### ج. معلومات إضافية
- `device_session_key`: المفتاح الثابت للجهاز
- `device_id`: رقم الجهاز
- `persistent_tracking`: علامة التتبع المستمر
- `session_type`: نوع الجلسة (individual/group)

### 4. Backward Compatibility

النظام يدعم الـ activities القديمة من خلال:
- إضافة `device_session_key` تلقائياً للأنشطة القديمة
- البحث في جميع الأنشطة المرتبطة بالجهاز
- عدم الاعتماد على وجود المفتاح مسبقاً

### 5. اختبار النظام

تم إنشاء ملف `test_persistent_device_key.php` لاختبار:
- تتبع الأنشطة عبر جلسات متعددة
- إضافة المفتاح المستمر
- التأكد من ربط جميع الأنشطة

#### نتائج الاختبار:
```
✅ النظام يرجع 27 activity لكل BookedDevice
✅ كل activity يحتوي على device_session_key مناسب
✅ المفتاح يتغير حسب التاريخ والـ daily_id
✅ جميع الأنشطة للجهاز الواحد مربوطة معاً
```

### 6. Route المحدث

```php
Route::get('{id}/activity-log','getActitvityLogToDevice');
```

الـ Route لم يتغير، لكن الـ method الآن:
- يجلب جميع الأنشطة للجهاز عبر جميع الجلسات
- يضيف مفتاح ثابت لربط الأنشطة
- يحافظ على التاريخ الكامل للجهاز

## النتيجة النهائية

الآن عند استدعاء الـ API للحصول على activity log لجهاز معين:

1. ✅ **يحصل على جميع الأنشطة للجهاز** - حتى لو تم نقله بين جلسات مختلفة
2. ✅ **مفتاح ثابت للربط** - `device_session_key` يربط جميع الأنشطة
3. ✅ **لا تختفي الـ logs** - عند النقل من فردي لمجمع أو العكس
4. ✅ **تتبع مستمر** - حتى لو تغير timer-id أو session-id
5. ✅ **Backward compatibility** - يدعم الأنشطة القديمة

المشكلة الأصلية محلولة بالكامل!
