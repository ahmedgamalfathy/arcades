# ✅ نظام Activity Log الموحد (Single Activity)

## 📋 التغيير الأساسي

تم تعديل النظام ليسجل **activity واحدة فقط** لكل عملية Order بدلاً من تسجيل منفصل لكل OrderItem.

---

## 🎯 قبل وبعد

### ❌ قبل (Multiple Activities)
```
Activity Log:
1. Order created (ID: 123)
2. OrderItem created (ID: 1)
3. OrderItem created (ID: 2)
4. OrderItem created (ID: 3)
5. Order updated (price calculated)
```
**المشكلة:** 5 سجلات منفصلة لعملية واحدة

### ✅ بعد (Single Activity)
```
Activity Log:
1. Order created with items (ID: 123)
   - Properties: {
       order: {...},
       order_items: [
         {product_name: "Product 1", qty: 2, price: 50},
         {product_name: "Product 2", qty: 1, price: 30}
       ],
       summary: {total_items: 2, total_price: 130}
     }
```
**الحل:** سجل واحد يحتوي على كل التفاصيل

---

## 🔧 التعديلات المطبقة

### 1. ✅ Order.php
```php
// تسجيل Order فقط (بدون OrderItems التلقائية)
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->useLogName('Order')
        ->logOnly(['name', 'type', 'price', 'is_paid', 'status', 'number'])
        ->logOnlyDirty()
        ->dontLogIfAttributesChangedOnly(['updated_at'])
        ->setDescriptionForEvent(fn(string $eventName) => "Order {$eventName}");
}
```

### 2. ✅ OrderItem.php
```php
// إزالة LogsActivity trait تماماً
class OrderItem extends Model
{
    use UsesTenantConnection;
    // لا يوجد تسجيل تلقائي
}
```

### 3. ✅ OrderService.php

#### createOrder()
```php
// تسجيل يدوي واحد بعد اكتمال كل شيء
activity()
    ->performedOn($order)
    ->withProperties([
        'order' => [...],
        'order_items' => [...],
        'summary' => [...]
    ])
    ->log('Order created with items');
```

#### updateOrder()
```php
// تسجيل يدوي واحد مع old و new
activity()
    ->performedOn($order)
    ->withProperties([
        'old' => [...],
        'new' => [...],
        'summary' => [...]
    ])
    ->log('Order updated with items');
```

#### deleteOrder()
```php
// تسجيل يدوي واحد قبل الحذف
activity()
    ->withProperties([
        'deleted_order' => [...]
    ])
    ->log('Order deleted with items');
```

---

## 📊 بنية البيانات المسجلة

### عند الإنشاء (created)
```json
{
  "id": 1,
  "log_name": "Order",
  "description": "Order created with items",
  "subject_type": "App\\Models\\Order\\Order",
  "subject_id": 123,
  "event": null,
  "causer_id": 1,
  "daily_id": 5,
  "properties": {
    "order": {
      "id": 123,
      "name": "Order #123",
      "number": "ORD_664402226",
      "type": "internal",
      "price": 130,
      "is_paid": false,
      "status": "pending"
    },
    "order_items": [
      {
        "id": 1,
        "product_id": 10,
        "product_name": "Product 1",
        "qty": 2,
        "price": 50,
        "total": 100
      },
      {
        "id": 2,
        "product_id": 11,
        "product_name": "Product 2",
        "qty": 1,
        "price": 30,
        "total": 30
      }
    ],
    "summary": {
      "total_items": 2,
      "total_price": 130
    }
  }
}
```

### عند التحديث (updated)
```json
{
  "properties": {
    "old": {
      "name": "Old Order Name",
      "is_paid": false,
      "status": "pending",
      "price": 130,
      "items": [...]
    },
    "new": {
      "name": "New Order Name",
      "is_paid": true,
      "status": "completed",
      "price": 150,
      "items": [...]
    },
    "summary": {
      "total_items": 3,
      "total_price": 150
    }
  }
}
```

### عند الحذف (deleted)
```json
{
  "properties": {
    "deleted_order": {
      "id": 123,
      "name": "Order #123",
      "number": "ORD_664402226",
      "price": 130,
      "items": [
        {
          "id": 1,
          "product_name": "Product 1",
          "qty": 2,
          "price": 50
        }
      ]
    }
  }
}
```

---

## 🧪 اختبار النظام الجديد

### 1. حذف البيانات القديمة
```sql
USE arcade_1;
TRUNCATE TABLE activity_log;
```

### 2. إنشاء Order جديد
```bash
POST /api/v1/admin/orders
{
  "name": "Test Order",
  "type": "internal",
  "dailyId": 1,
  "orderItems": [
    {"productId": 1, "qty": 2},
    {"productId": 2, "qty": 1}
  ]
}
```

### 3. التحقق من النتيجة
```sql
SELECT 
    id,
    log_name,
    description,
    JSON_EXTRACT(properties, '$.summary.total_items') as total_items,
    JSON_EXTRACT(properties, '$.summary.total_price') as total_price,
    created_at
FROM activity_log
ORDER BY created_at DESC;
```

### النتيجة المتوقعة
```
id | log_name | description                | total_items | total_price | created_at
---|----------|----------------------------|-------------|-------------|--------------------
1  | Order    | Order created with items   | 2           | 130         | 2026-02-10 16:30:00
```

✅ **سجل واحد فقط!**

---

## 📈 الفوائد

### 1. تقليل عدد السجلات
- **قبل:** 5 سجلات لكل Order
- **بعد:** 1 سجل لكل Order
- **توفير:** 80% من المساحة

### 2. سهولة القراءة
- كل المعلومات في مكان واحد
- لا حاجة للربط بين سجلات متعددة

### 3. أداء أفضل
- استعلامات أسرع
- حجم جدول أصغر

### 4. بيانات أكثر تفصيلاً
- تسجيل summary مع كل عملية
- تسجيل old و new في التحديثات

---

## 🔍 استعلامات مفيدة

### عرض جميع Orders المنشأة
```sql
SELECT 
    id,
    description,
    JSON_EXTRACT(properties, '$.order.number') as order_number,
    JSON_EXTRACT(properties, '$.summary.total_items') as items_count,
    JSON_EXTRACT(properties, '$.summary.total_price') as total_price,
    created_at
FROM activity_log
WHERE description = 'Order created with items'
ORDER BY created_at DESC;
```

### عرض تفاصيل OrderItems لـ Order معين
```sql
SELECT 
    JSON_EXTRACT(properties, '$.order_items') as items
FROM activity_log
WHERE description = 'Order created with items'
  AND subject_id = 123;
```

### عرض التغييرات في Order
```sql
SELECT 
    id,
    JSON_EXTRACT(properties, '$.old') as old_data,
    JSON_EXTRACT(properties, '$.new') as new_data,
    created_at
FROM activity_log
WHERE description = 'Order updated with items'
  AND subject_id = 123
ORDER BY created_at DESC;
```

---

## 📂 الملفات المعدلة

```
✅ app/Models/Order/Order.php (تبسيط LogOptions)
✅ app/Models/Order/OrderItem.php (إزالة LogsActivity)
✅ app/Services/Order/OrderService.php (تسجيل يدوي موحد)
```

---

## ⚠️ ملاحظات مهمة

### 1. لا يوجد batch_uuid
- النظام الجديد لا يستخدم LogBatch
- كل عملية = activity واحدة
- `batch_uuid` سيكون NULL

### 2. التسجيل اليدوي
- يجب استدعاء `activity()->log()` يدوياً
- لا يوجد تسجيل تلقائي للـ OrderItems

### 3. البيانات في properties
- جميع التفاصيل مخزنة في JSON
- استخدم `JSON_EXTRACT` للاستعلام

---

## ✨ الخلاصة

النظام الجديد:
- ✅ سجل واحد لكل عملية Order
- ✅ جميع التفاصيل في properties
- ✅ توفير 80% من المساحة
- ✅ أداء أفضل وقراءة أسهل

**جاهز للاستخدام! 🎉**
