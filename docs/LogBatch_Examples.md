# أمثلة عملية على استخدام LogBatch

## 1. مثال: إنشاء Order مع OrderItems

### الكود
```php
use Spatie\Activitylog\Facades\LogBatch;

public function createOrder(array $data)
{
    // بداية Batch - جميع العمليات التالية ستكون مجمعة
    LogBatch::startBatch();
    
    try {
        // إنشاء Order
        $order = Order::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'daily_id' => $data['dailyId'],
        ]);
        
        // إنشاء OrderItems
        foreach ($data['orderItems'] as $itemData) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $itemData['productId'],
                'qty' => $itemData['qty'],
            ]);
        }
        
        // تحديث السعر الإجمالي
        $order->update(['price' => $totalPrice]);
        
        // نهاية Batch - تم تسجيل جميع العمليات بنجاح
        LogBatch::endBatch();
        
        return $order;
        
    } catch (\Exception $e) {
        // في حالة الخطأ، يجب إنهاء الـ batch
        LogBatch::endBatch();
        throw $e;
    }
}
```

### النتيجة في قاعدة البيانات
```sql
SELECT id, log_name, event, description, batch_uuid, created_at
FROM activity_log
WHERE batch_uuid = '9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b'
ORDER BY created_at;
```

| id  | log_name  | event   | description       | batch_uuid                           | created_at          |
|-----|-----------|---------|-------------------|--------------------------------------|---------------------|
| 123 | Order     | created | Order created     | 9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | 2026-02-10 14:30:00 |
| 124 | OrderItem | created | OrderItem created | 9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | 2026-02-10 14:30:01 |
| 125 | OrderItem | created | OrderItem created | 9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | 2026-02-10 14:30:01 |
| 126 | Order     | updated | Order updated     | 9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | 2026-02-10 14:30:02 |

---

## 2. مثال: تحديث Order مع OrderItems

### الكود
```php
public function updateOrder(int $id, array $data)
{
    LogBatch::startBatch();
    
    $order = Order::findOrFail($id);
    
    // تحديث Order
    $order->update([
        'name' => $data['name'],
        'status' => $data['status'],
    ]);
    
    // معالجة OrderItems
    foreach ($data['orderItems'] as $itemData) {
        if ($itemData['actionStatus'] === 'create') {
            // إنشاء OrderItem جديد
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $itemData['productId'],
                'qty' => $itemData['qty'],
            ]);
        }
        
        if ($itemData['actionStatus'] === 'update') {
            // تحديث OrderItem موجود
            $orderItem = OrderItem::find($itemData['orderItemId']);
            $orderItem->update(['qty' => $itemData['qty']]);
        }
        
        if ($itemData['actionStatus'] === 'delete') {
            // حذف OrderItem
            OrderItem::find($itemData['orderItemId'])->delete();
        }
    }
    
    // إعادة حساب السعر
    $order->update(['price' => $order->items()->sum(DB::raw('price * qty'))]);
    
    LogBatch::endBatch();
    
    return $order;
}
```

### النتيجة
جميع العمليات (تحديث Order + إنشاء/تحديث/حذف OrderItems) ستكون تحت batch_uuid واحد.

---

## 3. مثال: استخدام LogBatch مع Transactions

### الكود الموصى به
```php
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\LogBatch;

public function createOrderWithTransaction(array $data)
{
    // بداية Transaction و Batch معاً
    DB::beginTransaction();
    LogBatch::startBatch();
    
    try {
        $order = Order::create($data);
        
        foreach ($data['orderItems'] as $itemData) {
            OrderItem::create([
                'order_id' => $order->id,
                ...$itemData
            ]);
        }
        
        // Commit Transaction
        DB::commit();
        
        // إنهاء Batch بنجاح
        LogBatch::endBatch();
        
        return $order;
        
    } catch (\Exception $e) {
        // Rollback Transaction
        DB::rollBack();
        
        // إنهاء Batch (سيتم تسجيل ما تم قبل الخطأ)
        LogBatch::endBatch();
        
        throw $e;
    }
}
```

---

## 4. مثال: LogBatch مع BookedDevice و SessionDevice

### السيناريو: إنشاء حجز جماعي مع عدة أجهزة

```php
public function createGroupBooking(array $data)
{
    LogBatch::startBatch();
    
    // إنشاء SessionDevice (الحجز الجماعي)
    $session = SessionDevice::create([
        'name' => $data['name'],
        'daily_id' => $data['dailyId'],
        'start_time' => now(),
    ]);
    
    // إنشاء BookedDevice لكل جهاز
    foreach ($data['devices'] as $deviceData) {
        BookedDevice::create([
            'session_device_id' => $session->id,
            'device_id' => $deviceData['deviceId'],
            'device_time_id' => $deviceData['deviceTimeId'],
            'start_time' => now(),
        ]);
    }
    
    LogBatch::endBatch();
    
    return $session;
}
```

### النتيجة
```
Batch UUID: abc-123-def
├── SessionDevice created (ID: 10)
├── BookedDevice created (ID: 50) - Device: PS5 #1
├── BookedDevice created (ID: 51) - Device: PS5 #2
└── BookedDevice created (ID: 52) - Device: Xbox #1
```

---

## 5. مثال: LogBatch مع Daily Operations

### السيناريو: إغلاق اليوم مع حساب الإيرادات والمصروفات

```php
public function closeDaily(int $dailyId)
{
    LogBatch::startBatch();
    
    $daily = Daily::findOrFail($dailyId);
    
    // حساب الإيرادات
    $totalRevenue = Order::where('daily_id', $dailyId)
        ->where('is_paid', true)
        ->sum('price');
    
    $sessionRevenue = SessionDevice::where('daily_id', $dailyId)
        ->sum('price');
    
    // حساب المصروفات
    $totalExpenses = Expense::where('daily_id', $dailyId)
        ->sum('amount');
    
    // تحديث Daily
    $daily->update([
        'total_revenue' => $totalRevenue + $sessionRevenue,
        'total_expenses' => $totalExpenses,
        'net_profit' => ($totalRevenue + $sessionRevenue) - $totalExpenses,
        'status' => 'closed',
        'closed_at' => now(),
    ]);
    
    LogBatch::endBatch();
    
    return $daily;
}
```

---

## 6. مثال: عرض Batch في Frontend

### استعلام API
```javascript
// الحصول على جميع batches لـ Order معين
const response = await fetch('/api/v1/admin/batched-activities/order/45');
const data = await response.json();

// عرض البيانات
data.data.forEach(batch => {
    console.log(`Batch: ${batch.batchUuid}`);
    console.log(`Summary: ${batch.summary}`);
    console.log(`Activities: ${batch.activitiesCount}`);
    console.log(`User: ${batch.userName}`);
    console.log(`Time: ${batch.startedAt} - ${batch.endedAt}`);
    
    batch.activities.forEach(activity => {
        console.log(`  - ${activity.description}`);
    });
});
```

### مثال على الناتج
```
Batch: 9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b
Summary: إنشاء 1 Order + إنشاء 3 OrderItem + تحديث 1 Order
Activities: 5
User: Ahmed
Time: 2026-02-10 14:30:00 - 2026-02-10 14:30:02
  - Order created
  - OrderItem created
  - OrderItem created
  - OrderItem created
  - Order updated
```

---

## 7. مثال: استعلامات مفيدة

### الحصول على آخر 10 batches
```php
$latestBatches = Activity::selectRaw('
        batch_uuid,
        MIN(created_at) as started_at,
        MAX(created_at) as ended_at,
        COUNT(*) as activities_count
    ')
    ->whereNotNull('batch_uuid')
    ->groupBy('batch_uuid')
    ->orderByDesc('started_at')
    ->limit(10)
    ->get();
```

### الحصول على أكبر batch (أكثر عدد من الأنشطة)
```php
$largestBatch = Activity::selectRaw('
        batch_uuid,
        COUNT(*) as activities_count
    ')
    ->whereNotNull('batch_uuid')
    ->groupBy('batch_uuid')
    ->orderByDesc('activities_count')
    ->first();
```

### الحصول على batches لمستخدم معين
```php
$userBatches = Activity::selectRaw('
        batch_uuid,
        MIN(created_at) as started_at,
        COUNT(*) as activities_count
    ')
    ->whereNotNull('batch_uuid')
    ->where('causer_id', $userId)
    ->groupBy('batch_uuid')
    ->orderByDesc('started_at')
    ->get();
```

---

## 8. Best Practices

### ✅ افعل
```php
// استخدم try-catch لضمان إنهاء الـ batch
LogBatch::startBatch();
try {
    // operations
    LogBatch::endBatch();
} catch (\Exception $e) {
    LogBatch::endBatch();
    throw $e;
}
```

### ❌ لا تفعل
```php
// لا تنسى إنهاء الـ batch
LogBatch::startBatch();
// operations
// نسيت LogBatch::endBatch() ❌
```

```php
// لا تستخدم batches متداخلة بدون داعي
LogBatch::startBatch();
    LogBatch::startBatch(); // ❌ تداخل غير ضروري
        // operations
    LogBatch::endBatch();
LogBatch::endBatch();
```

---

## الخلاصة

LogBatch يساعدك على:
- ✅ تجميع العمليات المترابطة
- ✅ فهم السياق الكامل للعملية
- ✅ تسهيل التتبع والتحليل
- ✅ تحسين تجربة المستخدم في عرض السجلات
