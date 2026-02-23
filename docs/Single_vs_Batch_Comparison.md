# 🔄 مقارنة: Single Activity vs Batch Activities

## نظرة عامة

هذا المستند يقارن بين نظامين لتسجيل الأنشطة:
1. **Single Activity** (النظام الحالي) - سجل واحد لكل عملية
2. **Batch Activities** (النظام السابق) - عدة سجلات مجمعة

---

## 📊 المقارنة التفصيلية

### 1. عدد السجلات

| العملية | Single Activity | Batch Activities |
|---------|----------------|------------------|
| إنشاء Order مع 3 items | **1 سجل** | 5 سجلات (1 Order + 3 Items + 1 Update) |
| تحديث Order مع items | **1 سجل** | 3-10 سجلات (حسب التغييرات) |
| حذف Order | **1 سجل** | 4 سجلات (1 Order + 3 Items) |

**الفائز:** Single Activity (توفير 80%)

---

### 2. بنية البيانات

#### Single Activity
```json
{
  "id": 1,
  "description": "Order created with items",
  "properties": {
    "order": {...},
    "order_items": [...],
    "summary": {...}
  }
}
```
**المزايا:**
- ✅ كل شيء في مكان واحد
- ✅ سهل القراءة
- ✅ لا حاجة للربط

**العيوب:**
- ❌ JSON كبير
- ❌ صعوبة الاستعلام عن item معين

#### Batch Activities
```json
[
  {"id": 1, "description": "Order created", "batch_uuid": "abc-123"},
  {"id": 2, "description": "OrderItem created", "batch_uuid": "abc-123"},
  {"id": 3, "description": "OrderItem created", "batch_uuid": "abc-123"}
]
```
**المزايا:**
- ✅ سجلات منفصلة لكل entity
- ✅ سهل الاستعلام عن item معين
- ✅ تتبع دقيق لكل تغيير

**العيوب:**
- ❌ عدد سجلات أكبر
- ❌ يحتاج ربط بـ batch_uuid

---

### 3. الأداء

#### Single Activity
```sql
-- استعلام بسيط
SELECT * FROM activity_log WHERE subject_id = 123;
-- النتيجة: 1 سجل
-- الوقت: ~2ms
```

#### Batch Activities
```sql
-- استعلام معقد
SELECT * FROM activity_log 
WHERE batch_uuid IN (
  SELECT DISTINCT batch_uuid 
  FROM activity_log 
  WHERE subject_id = 123
);
-- النتيجة: 5 سجلات
-- الوقت: ~8ms
```

**الفائز:** Single Activity (أسرع 4x)

---

### 4. حجم قاعدة البيانات

#### مثال: 1000 Order

| النظام | عدد السجلات | الحجم التقريبي |
|--------|-------------|----------------|
| Single Activity | 1,000 | ~5 MB |
| Batch Activities | 5,000 | ~15 MB |

**الفائز:** Single Activity (توفير 67%)

---

### 5. سهولة الاستخدام

#### Single Activity
```php
// بسيط - استعلام واحد
$activity = Activity::where('subject_id', $orderId)->first();
$items = $activity->properties['order_items'];
```

#### Batch Activities
```php
// معقد - استعلامات متعددة
$batchUuid = Activity::where('subject_id', $orderId)->value('batch_uuid');
$activities = Activity::where('batch_uuid', $batchUuid)->get();
$items = $activities->where('log_name', 'OrderItem');
```

**الفائز:** Single Activity (أبسط)

---

### 6. التفاصيل والدقة

#### Single Activity
```json
{
  "order_items": [
    {"product_name": "Product 1", "qty": 2, "price": 50}
  ],
  "summary": {"total_items": 1, "total_price": 50}
}
```
**المزايا:**
- ✅ ملخص شامل
- ✅ معلومات إضافية (summary)

**العيوب:**
- ❌ لا يظهر تسلسل العمليات بدقة

#### Batch Activities
```json
[
  {"event": "created", "created_at": "14:30:00"},
  {"event": "created", "created_at": "14:30:01"},
  {"event": "updated", "created_at": "14:30:02"}
]
```
**المزايا:**
- ✅ تسلسل دقيق للعمليات
- ✅ timestamp لكل عملية

**العيوب:**
- ❌ لا يوجد ملخص شامل

**الفائز:** تعادل (كل نظام له مزاياه)

---

### 7. الاستعلامات

#### Single Activity
```sql
-- عرض جميع Orders
SELECT * FROM activity_log 
WHERE description = 'Order created with items';

-- عرض Orders لـ Daily معين
SELECT * FROM activity_log 
WHERE daily_id = 5 
  AND description LIKE 'Order%';

-- عرض تفاصيل Order
SELECT 
  JSON_EXTRACT(properties, '$.order_items') as items
FROM activity_log 
WHERE subject_id = 123;
```

#### Batch Activities
```sql
-- عرض جميع Batches
SELECT DISTINCT batch_uuid 
FROM activity_log 
WHERE batch_uuid IS NOT NULL;

-- عرض Batches لـ Daily معين
SELECT DISTINCT batch_uuid 
FROM activity_log 
WHERE daily_id = 5 
  AND batch_uuid IS NOT NULL;

-- عرض تفاصيل Batch
SELECT * FROM activity_log 
WHERE batch_uuid = 'abc-123' 
ORDER BY created_at;
```

**الفائز:** Single Activity (استعلامات أبسط)

---

## 🎯 متى تستخدم كل نظام؟

### استخدم Single Activity عندما:
- ✅ تريد توفير المساحة
- ✅ تريد أداء أفضل
- ✅ تريد استعلامات بسيطة
- ✅ لا تحتاج تتبع دقيق لكل عملية فرعية
- ✅ تريد ملخص شامل لكل عملية

### استخدم Batch Activities عندما:
- ✅ تحتاج تتبع دقيق لكل عملية
- ✅ تريد معرفة تسلسل العمليات بالضبط
- ✅ تحتاج استعلام عن items منفصلة
- ✅ تريد إمكانية التراجع عن batch كامل
- ✅ تحتاج audit trail مفصل

---

## 📈 الإحصائيات

### Single Activity
- **عدد السجلات:** 1 لكل عملية
- **الحجم:** صغير (JSON واحد)
- **الأداء:** ممتاز
- **التعقيد:** بسيط
- **الاستخدام:** مثالي للتطبيقات العادية

### Batch Activities
- **عدد السجلات:** 3-10 لكل عملية
- **الحجم:** متوسط (عدة سجلات)
- **الأداء:** جيد (مع indexes)
- **التعقيد:** متوسط
- **الاستخدام:** مثالي للتطبيقات المعقدة

---

## 🔄 التحويل بين النظامين

### من Batch إلى Single
```php
// قبل
LogBatch::startBatch();
$order = Order::create([...]);
foreach ($items as $item) {
    OrderItem::create([...]);
}
LogBatch::endBatch();

// بعد
$order = Order::create([...]);
foreach ($items as $item) {
    OrderItem::create([...]);
}
activity()->performedOn($order)
    ->withProperties([...])
    ->log('Order created with items');
```

### من Single إلى Batch
```php
// قبل
activity()->performedOn($order)
    ->withProperties([...])
    ->log('Order created with items');

// بعد
LogBatch::startBatch();
$order = Order::create([...]);
foreach ($items as $item) {
    OrderItem::create([...]);
}
LogBatch::endBatch();
```

---

## ✨ الخلاصة

| المعيار | Single Activity | Batch Activities |
|---------|----------------|------------------|
| عدد السجلات | ⭐⭐⭐⭐⭐ | ⭐⭐ |
| الأداء | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| حجم البيانات | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| سهولة الاستخدام | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| التفاصيل | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| التتبع الدقيق | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

**التوصية:**
- للتطبيقات العادية: **Single Activity** ✅
- للتطبيقات المعقدة: **Batch Activities**

**النظام الحالي:** Single Activity ✅
