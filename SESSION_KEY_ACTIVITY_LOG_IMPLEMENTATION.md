# تحسين Activity Log للأجهزة - إضافة Session Key

## المشكلة
عند التحويل بين أنواع الجلسات المختلفة (من نوع 0 إلى 1 أو العكس أو بين الأنواع المجمعة)، كان النظام يجلب جميع الـ logs الخاصة بالجهاز من جميع الجلسات السابقة، مما يؤدي إلى إحضار logs قديمة من أيام مضت.

## الحل المطبق

### 1. إضافة Session Key
تم إضافة مفتاح وهمي (`session_key`) في الـ activity logs يربط جميع الأنشطة المتعلقة بنفس الجلسة المفتوحة:

#### صيغة Session Key:
- **للجلسات الفردية**: `individual_{device_id}_{timestamp}`
- **للجلسات المجمعة**: `session_{session_id}_{timestamp}`

### 2. التحديثات المطبقة

#### في `BookedDeviceService.php`:

##### أ. Method `getActivityLogToDevice()` - محدث بالكامل
```php
public function getActivityLogToDevice($bookedDeviceId)
{
    // Get the current BookedDevice
    $currentBookedDevice = BookedDevice::findOrFail($bookedDeviceId);
    
    // Find the session key for the current active session
    $sessionKey = $this->findCurrentSessionKey($currentBookedDevice);
    
    // Get only activities for the current active session using session_key
    $activities = Activity::where(function ($query) use ($sessionKey, $bookedDeviceId) {
        $query->where(function ($q) use ($sessionKey) {
            // Get activities that have the same session_key
            $q->whereJsonContains('properties->session_key', $sessionKey);
        })
        ->orWhere(function ($q) use ($bookedDeviceId) {
            // Also include direct activities for this specific booked device
            // that might not have session_key yet (for backward compatibility)
            $q->where('subject_type', BookedDevice::class)
              ->where('subject_id', $bookedDeviceId)
              ->whereDate('created_at', today()); // Only today's activities
        });
    })
    ->orderBy('created_at', 'desc')
    ->get();
    
    // Add session_key to activities that don't have it
    $activities = $activities->map(function ($activity) use ($sessionKey) {
        $properties = is_string($activity->properties) 
            ? json_decode($activity->properties, true) 
            : ($activity->properties ?? []);
        
        if (!isset($properties['session_key'])) {
            $properties['session_key'] = $sessionKey;
            $activity->properties = $properties;
        }
        
        return $activity;
    });
    
    // Group activities by session_key for better organization
    return $this->groupActivitiesBySessionKey($activities, $sessionKey);
}
```

##### ب. Helper Methods الجديدة
```php
/**
 * Find the current session key for a booked device
 */
private function findCurrentSessionKey($bookedDevice)
{
    // Check if device is in a session (group)
    if ($bookedDevice->session_device_id) {
        $sessionDevice = SessionDevice::find($bookedDevice->session_device_id);
        if ($sessionDevice) {
            // Use session start time + session_id as unique key
            return 'session_' . $sessionDevice->id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s');
        }
    }
    
    // For individual devices, use booked device start time + device_id
    return 'individual_' . $bookedDevice->device_id . '_' . $bookedDevice->created_at->format('Y-m-d_H-i-s');
}

/**
 * Group activities by session key for better organization
 */
private function groupActivitiesBySessionKey($activities, $sessionKey)
{
    // Filter activities to only include those with matching session_key or recent activities
    $filteredActivities = $activities->filter(function ($activity) use ($sessionKey) {
        $properties = is_string($activity->properties) 
            ? json_decode($activity->properties, true) 
            : ($activity->properties ?? []);
        
        // Include if has matching session_key or is from today
        return (isset($properties['session_key']) && $properties['session_key'] === $sessionKey) ||
               $activity->created_at->isToday();
    });
    
    // Add session metadata to each activity
    return $filteredActivities->map(function ($activity) use ($sessionKey) {
        $properties = is_string($activity->properties) 
            ? json_decode($activity->properties, true) 
            : ($activity->properties ?? []);
        
        $properties['session_key'] = $sessionKey;
        $properties['session_group'] = true; // Mark as part of session group
        
        $activity->properties = $properties;
        return $activity;
    });
}
```

##### ج. تحديث Methods الأخرى لإضافة Session Key:

**transferDeviceToGroup():**
```php
'session_key' => $newSessionKey,
'transfer_type' => 'to_group'
```

**transferBookedDeviceToSessionDevice():**
```php
'session_key' => 'individual_' . $bookedDevice->device_id . '_' . $newSessionDevice->created_at->format('Y-m-d_H-i-s'),
'transfer_type' => 'to_individual'
```

**updateEndDateTime():**
```php
'session_key' => $sessionKey,
'update_type' => 'end_time'
```

**deleteSessionWithLogging():**
```php
'session_key' => $sessionDevice->type === SessionDeviceEnum::INDIVIDUAL->value 
    ? 'individual_' . ($bookedDevices[0]->device_id ?? 'unknown') . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s')
    : 'session_' . $sessionDevice->id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s'),
'delete_type' => 'session_with_devices'
```

#### في `DeviceTimerController.php`:

##### أ. Method `individualTime()` - محدث
```php
$sessionKey = 'individual_' . $device->device_id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s');

// في properties:
'session_key' => $sessionKey,
'session_type' => 'individual'
```

##### ب. Method `groupTime()` - محدث
```php
$sessionKey = 'session_' . $sessionDevice->id . '_' . $sessionDevice->created_at->format('Y-m-d_H-i-s');

// في properties:
'session_key' => $sessionKey,
'session_type' => 'group'
```

### 3. المزايا الجديدة

#### أ. تصفية دقيقة للـ Activities
- يجلب فقط الأنشطة المتعلقة بالجلسة المفتوحة الحالية
- لا يحضر logs من جلسات سابقة أو أيام مضت
- يستخدم `whereDate('created_at', today())` كـ fallback للـ backward compatibility

#### ب. ربط الأنشطة
- جميع الأنشطة المتعلقة بنفس الجلسة مربوطة بـ session_key واحد
- سهولة تتبع الأنشطة عند التحويل بين أنواع الجلسات المختلفة

#### ج. معلومات إضافية
- `session_type`: نوع الجلسة (individual/group)
- `transfer_type`: نوع التحويل (to_group/to_individual)
- `update_type`: نوع التحديث (end_time)
- `delete_type`: نوع الحذف (session_with_devices)

### 4. Route المحدث
```php
Route::get('{id}/activity-log','getActitvityLogToDevice');
```
الـ Route لم يتغير، لكن الـ method الآن يجلب فقط الأنشطة المتعلقة بالجلسة المفتوحة.

### 5. اختبار التطبيق
تم إنشاء ملف `test_session_key_activity_log.php` لاختبار:
- إنشاء جلسات فردية ومجمعة
- جلب activity logs مع session_key
- عمليات التحويل مع session_key
- التأكد من صحة التصفية

### 6. Backward Compatibility
النظام يدعم الـ logs القديمة التي لا تحتوي على session_key من خلال:
- إضافة session_key تلقائياً للأنشطة القديمة
- استخدام `whereDate('created_at', today())` للأنشطة الحديثة بدون session_key

## النتيجة
الآن عند استدعاء الـ API للحصول على activity log لجهاز معين، سيحصل المستخدم فقط على الأنشطة المتعلقة بالجلسة المفتوحة الحالية، مع ربط جميع الأنشطة تحت session_key واحد، دون إحضار logs من أيام أو جلسات سابقة.
