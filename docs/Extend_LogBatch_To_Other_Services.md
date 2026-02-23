# 🚀 توسيع LogBatch إلى Services أخرى

## نظرة عامة

بعد تطبيق LogBatch بنجاح في `OrderService`, يمكنك تطبيقه في Services أخرى باتباع نفس النمط.

---

## 📋 Services المقترحة للتطبيق

### 1. ✅ OrderService (تم التطبيق)
- createOrder
- updateOrder
- deleteOrder
- restoreOrder
- forceDeleteOrder

### 2. 🎯 BookedDeviceService (مقترح)
- createBookedDevice
- updateBookedDevice
- finishBookedDevice
- pauseBookedDevice
- resumeBookedDevice

### 3. 🎯 SessionDeviceService (مقترح)
- createSessionDevice (مع عدة BookedDevices)
- updateSessionDevice
- endSessionDevice

### 4. 🎯 DailyService (مقترح)
- createDaily
- closeDaily (مع حساب الإيرادات والمصروفات)
- updateDaily

### 5. 🎯 ExpenseService (مقترح)
- createExpense
- updateExpense
- deleteExpense

---

## 📝 مثال 1: BookedDeviceService

### الكود الحالي (بدون LogBatch)
```php
public function createBookedDevice(array $data)
{
    $bookedDevice = BookedDevice::create([
        'device_id' => $data['deviceId'],
        'device_time_id' => $data['deviceTimeId'],
        'start_time' => now(),
    ]);
    
    // تحديث حالة الجهاز
    $device = Device::find($data['deviceId']);
    $device->update(['status' => 'busy']);
    
    return $bookedDevice;
}
```

### الكود المعدل (مع LogBatch)
```php
use Spatie\Activitylog\Facades\LogBatch;

public function createBookedDevice(array $data)
{
    LogBatch::startBatch();
    
    try {
        $bookedDevice = BookedDevice::create([
            'device_id' => $data['deviceId'],
            'device_time_id' => $data['deviceTimeId'],
            'start_time' => now(),
        ]);
        
        // تحديث حالة الجهاز
        $device = Device::find($data['deviceId']);
        $device->update(['status' => 'busy']);
        
        LogBatch::endBatch();
        
        return $bookedDevice;
        
    } catch (\Exception $e) {
        LogBatch::endBatch();
        throw $e;
    }
}
```

### النتيجة في Activity Log
```
Batch UUID: abc-123-def
├── BookedDevice created (ID: 10)
└── Device updated (ID: 5, status: available → busy)
```

---

## 📝 مثال 2: SessionDeviceService

### السيناريو: إنشاء حجز جماعي مع عدة أجهزة

```php
use Spatie\Activitylog\Facades\LogBatch;

public function createSessionDevice(array $data)
{
    LogBatch::startBatch();
    
    try {
        // إنشاء SessionDevice
        $session = SessionDevice::create([
            'name' => $data['name'],
            'daily_id' => $data['dailyId'],
            'start_time' => now(),
        ]);
        
        // إنشاء BookedDevice لكل جهاز
        foreach ($data['devices'] as $deviceData) {
            $bookedDevice = BookedDevice::create([
                'session_device_id' => $session->id,
                'device_id' => $deviceData['deviceId'],
                'device_time_id' => $deviceData['deviceTimeId'],
                'start_time' => now(),
            ]);
            
            // تحديث حالة الجهاز
            Device::find($deviceData['deviceId'])
                ->update(['status' => 'busy']);
        }
        
        LogBatch::endBatch();
        
        return $session;
        
    } catch (\Exception $e) {
        LogBatch::endBatch();
        throw $e;
    }
}
```

### النتيجة في Activity Log
```
Batch UUID: xyz-789-abc
├── SessionDevice created (ID: 5)
├── BookedDevice created (ID: 20) - Device: PS5 #1
├── Device updated (ID: 10, status: available → busy)
├── BookedDevice created (ID: 21) - Device: PS5 #2
├── Device updated (ID: 11, status: available → busy)
└── BookedDevice created (ID: 22) - Device: Xbox #1
└── Device updated (ID: 12, status: available → busy)
```

---

## 📝 مثال 3: DailyService - إغلاق اليوم

### السيناريو: إغلاق Daily مع حساب الإيرادات والمصروفات

```php
use Spatie\Activitylog\Facades\LogBatch;
use Illuminate\Support\Facades\DB;

public function closeDaily(int $dailyId)
{
    LogBatch::startBatch();
    
    try {
        $daily = Daily::findOrFail($dailyId);
        
        // حساب إيرادات Orders
        $orderRevenue = Order::where('daily_id', $dailyId)
            ->where('is_paid', true)
            ->sum('price');
        
        // حساب إيرادات Sessions
        $sessionRevenue = SessionDevice::where('daily_id', $dailyId)
            ->whereNotNull('end_time')
            ->sum('price');
        
        // حساب المصروفات
        $totalExpenses = Expense::where('daily_id', $dailyId)
            ->sum('amount');
        
        // تحديث Daily
        $daily->update([
            'order_revenue' => $orderRevenue,
            'session_revenue' => $sessionRevenue,
            'total_revenue' => $orderRevenue + $sessionRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => ($orderRevenue + $sessionRevenue) - $totalExpenses,
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        
        LogBatch::endBatch();
        
        return $daily;
        
    } catch (\Exception $e) {
        LogBatch::endBatch();
        throw $e;
    }
}
```

### النتيجة في Activity Log
```
Batch UUID: def-456-ghi
└── Daily updated (ID: 5)
    - order_revenue: 0 → 1500
    - session_revenue: 0 → 2500
    - total_revenue: 0 → 4000
    - total_expenses: 0 → 500
    - net_profit: 0 → 3500
    - status: open → closed
```

---

## 📝 مثال 4: ExpenseService

```php
use Spatie\Activitylog\Facades\LogBatch;

public function createExpense(array $data)
{
    LogBatch::startBatch();
    
    try {
        $expense = Expense::create([
            'type' => $data['type'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'daily_id' => $data['dailyId'],
        ]);
        
        // تحديث Daily إذا كان مفتوحاً
        $daily = Daily::find($data['dailyId']);
        if ($daily && $daily->status === 'open') {
            $totalExpenses = Expense::where('daily_id', $data['dailyId'])
                ->sum('amount');
            
            $daily->update([
                'total_expenses' => $totalExpenses,
                'net_profit' => $daily->total_revenue - $totalExpenses,
            ]);
        }
        
        LogBatch::endBatch();
        
        return $expense;
        
    } catch (\Exception $e) {
        LogBatch::endBatch();
        throw $e;
    }
}
```

---

## 🎯 النمط العام للتطبيق

### Template للاستخدام
```php
use Spatie\Activitylog\Facades\LogBatch;

public function yourMethod(array $data)
{
    LogBatch::startBatch();
    
    try {
        // 1. العملية الرئيسية
        $mainModel = MainModel::create($data);
        
        // 2. العمليات المرتبطة
        foreach ($data['relatedItems'] as $item) {
            RelatedModel::create([
                'main_model_id' => $mainModel->id,
                ...$item
            ]);
        }
        
        // 3. تحديثات إضافية
        $mainModel->update(['calculated_field' => $calculatedValue]);
        
        LogBatch::endBatch();
        
        return $mainModel;
        
    } catch (\Exception $e) {
        LogBatch::endBatch();
        throw $e;
    }
}
```

---

## ✅ Checklist للتطبيق

عند تطبيق LogBatch في Service جديد:

- [ ] إضافة `use Spatie\Activitylog\Facades\LogBatch;`
- [ ] إضافة `LogBatch::startBatch()` في بداية الـ method
- [ ] إضافة `LogBatch::endBatch()` في نهاية الـ method
- [ ] استخدام `try-catch` لضمان إنهاء الـ batch
- [ ] التأكد من أن Models تستخدم `LogsActivity` trait
- [ ] اختبار الـ method والتحقق من `batch_uuid` في قاعدة البيانات

---

## 📊 الفوائد المتوقعة

### قبل LogBatch
```
Activity Log (Linear):
1. SessionDevice created
2. BookedDevice created
3. Device updated
4. BookedDevice created
5. Device updated
6. BookedDevice created
7. Device updated
```
**المشكلة:** صعوبة معرفة أي BookedDevice يخص أي SessionDevice

### بعد LogBatch
```
Batch 1 (SessionDevice #5):
├── SessionDevice created
├── BookedDevice created (Device #10)
├── Device updated (Device #10)
├── BookedDevice created (Device #11)
├── Device updated (Device #11)
└── BookedDevice created (Device #12)
└── Device updated (Device #12)
```
**الحل:** واضح أن جميع العمليات مرتبطة بـ SessionDevice #5

---

## 🔍 استعلامات مفيدة بعد التطبيق

### عرض جميع batches لـ SessionDevice
```php
$sessionBatches = Activity::selectRaw('
        batch_uuid,
        MIN(created_at) as started_at,
        COUNT(*) as activities_count
    ')
    ->whereNotNull('batch_uuid')
    ->where('subject_type', 'App\\Models\\Timer\\SessionDevice\\SessionDevice')
    ->where('subject_id', $sessionId)
    ->groupBy('batch_uuid')
    ->orderByDesc('started_at')
    ->get();
```

### عرض جميع batches لـ Daily
```php
$dailyBatches = Activity::where('daily_id', $dailyId)
    ->whereNotNull('batch_uuid')
    ->orderBy('batch_uuid')
    ->orderBy('created_at')
    ->get()
    ->groupBy('batch_uuid');
```

---

## 🎯 الخطوات التالية

1. **اختر Service** من القائمة المقترحة
2. **طبق النمط** المذكور أعلاه
3. **اختبر** التطبيق
4. **تحقق** من `batch_uuid` في قاعدة البيانات
5. **كرر** للـ Services الأخرى

---

## 💡 نصائح

1. **ابدأ بالـ Services البسيطة** (مثل ExpenseService)
2. **استخدم try-catch دائماً** لضمان إنهاء الـ batch
3. **اختبر كل Service** بعد التطبيق
4. **وثق التغييرات** في كل Service

---

## ✨ الخلاصة

تطبيق LogBatch في Services أخرى:
- ✅ سهل ومباشر
- ✅ يتبع نفس النمط
- ✅ يحسن تتبع الأنشطة
- ✅ يسهل التحليل والتقارير

**ابدأ الآن بتطبيقه في Services أخرى! 🚀**
