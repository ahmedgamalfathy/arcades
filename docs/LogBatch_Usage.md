# استخدام LogBatch في نظام Activity Log

## نظرة عامة
تم تطبيق خاصية **LogBatch** من مكتبة `spatie/laravel-activitylog` لتجميع العمليات المترابطة في batch واحد بدلاً من تسجيلها بشكل منفصل (linear).

## الفوائد

### قبل استخدام LogBatch (Linear Logging)
```
Activity Log:
1. Order created (ID: 123)
2. OrderItem created (ID: 1)
3. OrderItem created (ID: 2)
4. OrderItem created (ID: 3)
5. Order updated (price calculated)
```
كل عملية تُسجل بشكل منفصل، مما يصعب تتبع العمليات المترابطة.

### بعد استخدام LogBatch (Batched Logging)
```
Activity Log Batch (UUID: abc-123-def):
├── Order created (ID: 123)
├── OrderItem created (ID: 1)
├── OrderItem created (ID: 2)
├── OrderItem created (ID: 3)
└── Order updated (price calculated)
```
جميع العمليات مجمعة تحت `batch_uuid` واحد، مما يسهل:
- تتبع العمليات المترابطة
- فهم السياق الكامل للعملية
- التراجع عن مجموعة عمليات كاملة
- تحليل الأداء

## التطبيق في OrderService

### 1. إنشاء Order جديد
```php
public function createOrder(array $data){
    LogBatch::startBatch(); // بداية Batch
    
    // إنشاء Order
    $order = Order::create([...]);
    
    // إنشاء OrderItems (كلها ستكون في نفس الـ batch)
    foreach ($data['orderItems'] as $itemData) {
        $item = $this->orderItemService->createOrderItem([...]);
    }
    
    // تحديث السعر الإجمالي
    $order->update(['price' => $totalPrice]);
    
    LogBatch::endBatch(); // نهاية Batch
    
    return $order;
}
```

### 2. تحديث Order
```php
public function updateOrder(int $id, array $data){
    LogBatch::startBatch();
    
    // تحديث Order
    $order->save();
    
    // تحديث/إنشاء/حذف OrderItems
    foreach ($data['orderItems'] as $itemData) {
        // جميع العمليات في نفس الـ batch
    }
    
    LogBatch::endBatch();
    
    return $order;
}
```

### 3. حذف Order
```php
public function deleteOrder(int $id){
    LogBatch::startBatch();
    
    $order->delete(); // سيحذف Order و OrderItems (cascade)
    
    LogBatch::endBatch();
}
```

## استعلام Activity Log مع Batch

### الحصول على جميع الأنشطة في batch واحد
```php
use Spatie\Activitylog\Models\Activity;

// الحصول على batch_uuid من أي activity
$activity = Activity::find(1);
$batchUuid = $activity->batch_uuid;

// الحصول على جميع الأنشطة في نفس الـ batch
$batchActivities = Activity::where('batch_uuid', $batchUuid)
    ->orderBy('created_at')
    ->get();

// عرض الأنشطة
foreach ($batchActivities as $activity) {
    echo $activity->description . "\n";
    echo "Subject: " . $activity->subject_type . " (ID: " . $activity->subject_id . ")\n";
    echo "Changes: " . json_encode($activity->changes) . "\n\n";
}
```

### مثال على البيانات المخزنة
```json
{
  "id": 1,
  "log_name": "Order",
  "description": "Order created",
  "subject_type": "App\\Models\\Order\\Order",
  "subject_id": 123,
  "batch_uuid": "9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b",
  "properties": {
    "attributes": {
      "name": "Order #123",
      "type": "internal",
      "price": 0
    }
  },
  "daily_id": 5
}
```

## الملفات المعدلة

### 1. OrderService.php
- ✅ `createOrder()` - يجمع Order + OrderItems + Update
- ✅ `updateOrder()` - يجمع Order update + OrderItems operations
- ✅ `deleteOrder()` - يجمع Order + OrderItems deletion
- ✅ `restoreOrder()` - يجمع Order + OrderItems restoration
- ✅ `forceDeleteOrder()` - يجمع الحذف النهائي

### 2. Models (Order.php & OrderItem.php)
- تم الاحتفاظ بإعدادات LogsActivity الحالية
- كل model يسجل تغييراته تلقائياً
- عند استخدام LogBatch، يتم ربطها بـ batch_uuid واحد

## ملاحظات مهمة

1. **التداخل (Nesting)**: يمكن تداخل Batches، لكن يُفضل تجنبه
2. **الأخطاء**: إذا حدث خطأ، يجب التأكد من إنهاء الـ batch
3. **Performance**: LogBatch لا يؤثر على الأداء بشكل ملحوظ
4. **Database**: يتم تخزين `batch_uuid` في جدول `activity_log`

## استعلامات مفيدة

### عدد الأنشطة في كل batch
```php
Activity::selectRaw('batch_uuid, COUNT(*) as count')
    ->whereNotNull('batch_uuid')
    ->groupBy('batch_uuid')
    ->orderByDesc('count')
    ->get();
```

### آخر 10 batches
```php
Activity::selectRaw('batch_uuid, MIN(created_at) as started_at, MAX(created_at) as ended_at, COUNT(*) as activities_count')
    ->whereNotNull('batch_uuid')
    ->groupBy('batch_uuid')
    ->orderByDesc('started_at')
    ->limit(10)
    ->get();
```

### الأنشطة المرتبطة بـ daily_id معين
```php
Activity::where('daily_id', 5)
    ->whereNotNull('batch_uuid')
    ->orderBy('batch_uuid')
    ->orderBy('created_at')
    ->get()
    ->groupBy('batch_uuid');
```
