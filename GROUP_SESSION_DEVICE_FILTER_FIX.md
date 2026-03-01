# إصلاح فلترة الأجهزة في الوقت المجمع

## المشكلة

في **الوقت المجمع** (group session)، عندما يكون عندنا جهازين أو أكثر في نفس الجلسة:

**مثال:**
- جهاز رقم 1 "بلايستيشن 3" (`device_id = 5`, `session_device_id = 20`)
- جهاز رقم 2 "بلايستيشن 5" (`device_id = 8`, `session_device_id = 20`)

**المشكلة الأولى:**
عند طلب activity log لجهاز رقم 1، كان يظهر أنشطة الجهازين معاً في الـ children!

**المشكلة الثانية:**
نشاط "قام بتحديث جلسة" كان يظهر مرتين:
- مرة للجهاز الأول
- مرة للجهاز الثاني

**السبب:**
- الكود كان يجلب كل الأنشطة للـ `session_device_id`
- لم يكن يفلتر الـ children حسب `device_id`
- كان يعرض كل SessionDevice activities حتى لو ما كان ليها children للجهاز المطلوب

## الحل

تم إضافة فلترة مزدوجة في `groupParentChildActivitiesForDevice`:

### 1. فلترة الـ children حسب device_id

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

### 2. فلترة الأنشطة نفسها

```php
// ✅ نعرض SessionDevice activity فقط إذا كان ليها children للجهاز المطلوب
if (!empty($activity->children)) {
    $grouped->push($activity);
}
```

هذا يمنع ظهور نشاط "قام بتحديث جلسة" للأجهزة الأخرى في نفس الجلسة.

## النتيجة

### قبل الإصلاح:

**جهاز 1:**
```json
[
  {
    "eventType": "created",
    "model": {"modelName": "SessionDevice"},
    "children": [
      {"deviceName": "بلايستيشن 3"},  // ✅ جهاز 1
      {"deviceName": "بلايستيشن 5"}   // ❌ جهاز 2
    ]
  },
  {
    "eventType": "updated",
    "model": {"modelName": "SessionDevice"},
    "children": [
      {"deviceName": "بلايستيشن 3"}   // ✅ جهاز 1
    ]
  },
  {
    "eventType": "updated",  // ❌ مكرر!
    "model": {"modelName": "SessionDevice"},
    "children": [
      {"deviceName": "بلايستيشن 5"}   // ❌ جهاز 2
    ]
  }
]
```

### بعد الإصلاح:

**جهاز 1:**
```json
[
  {
    "eventType": "created",
    "model": {"modelName": "SessionDevice"},
    "children": [
      {"deviceName": "بلايستيشن 3"}  // ✅ جهاز 1 فقط
    ]
  },
  {
    "eventType": "updated",
    "model": {"modelName": "SessionDevice"},
    "children": [
      {"deviceName": "بلايستيشن 3"}  // ✅ جهاز 1 فقط
    ]
  }
]
```

**جهاز 2:**
```json
[
  {
    "eventType": "created",
    "model": {"modelName": "SessionDevice"},
    "children": [
      {"deviceName": "بلايستيشن 5"}  // ✅ جهاز 2 فقط
    ]
  },
  {
    "eventType": "updated",
    "model": {"modelName": "SessionDevice"},
    "children": [
      {"deviceName": "بلايستيشن 5"}  // ✅ جهاز 2 فقط
    ]
  }
]
```

## الملفات المعدلة

- `app/Services/Timer/BookedDeviceService.php`
  - `getActivityLogToDevice()` - تمرير `device_id`
  - `groupParentChildActivitiesForDevice()` - إضافة فلترة مزدوجة:
    1. فلترة الـ children حسب `device_id`
    2. فلترة الأنشطة نفسها (عرض فقط اللي ليها children)

## الفوائد

1. ✅ فصل واضح بين أنشطة الأجهزة المختلفة في الوقت المجمع
2. ✅ كل جهاز يعرض أنشطته فقط
3. ✅ لا تكرار للأنشطة
4. ✅ لا تداخل بين الأجهزة
5. ✅ يعمل مع كل السيناريوهات (فردي، مجمع، تغيير وقت، جلسات متعددة)

## ملاحظات

- الفلترة المزدوجة تضمن عدم ظهور أنشطة فارغة
- SessionDevice activity يظهر فقط إذا كان له children للجهاز المطلوب
- هذا يمنع التكرار والتداخل بين الأجهزة في الوقت المجمع
