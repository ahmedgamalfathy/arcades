# 🧪 دليل اختبار LogBatch

## المتطلبات الأساسية

1. ✅ تم تثبيت Composer dependencies
2. ✅ قاعدة البيانات جاهزة
3. ✅ تم إضافة المسارات إلى `routes/api.php`
4. ✅ المستخدم مصادق عليه (Sanctum token)

---

## 🔍 اختبار 1: إنشاء Order مع LogBatch

### الخطوة 1: إنشاء Order جديد
```bash
POST http://127.0.0.1:8000/api/v1/admin/orders
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json

{
  "name": "Test Order #1",
  "type": "internal",
  "dailyId": 1,
  "isPaid": false,
  "status": "pending",
  "orderItems": [
    {
      "productId": 1,
      "qty": 2
    },
    {
      "productId": 2,
      "qty": 3
    }
  ]
}
```

### الخطوة 2: التحقق من قاعدة البيانات
```sql
-- عرض آخر batch تم إنشاؤه
SELECT 
    batch_uuid,
    log_name,
    event,
    description,
    subject_id,
    created_at
FROM activity_log
WHERE batch_uuid = (
    SELECT batch_uuid 
    FROM activity_log 
    WHERE batch_uuid IS NOT NULL 
    ORDER BY created_at DESC 
    LIMIT 1
)
ORDER BY created_at;
```

### النتيجة المتوقعة
```
batch_uuid                           | log_name  | event   | description       | subject_id
-------------------------------------|-----------|---------|-------------------|------------
9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | Order     | created | Order created     | 1
9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | OrderItem | created | OrderItem created | 1
9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | OrderItem | created | OrderItem created | 2
9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b | Order     | updated | Order updated     | 1
```

✅ **النجاح**: جميع الأنشطة لها نفس `batch_uuid`

---

## 🔍 اختبار 2: عرض Batches عبر API

### الخطوة 1: عرض جميع الـ Batches
```bash
GET http://127.0.0.1:8000/api/v1/admin/batched-activities
Authorization: Bearer YOUR_TOKEN_HERE
```

### النتيجة المتوقعة
```json
{
  "success": true,
  "message": "Batched activities retrieved successfully",
  "data": [
    {
      "batchUuid": "9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b",
      "userName": "Admin User",
      "userId": 1,
      "dailyId": 1,
      "startedAt": "2026-02-10 14:30:00",
      "endedAt": "2026-02-10 14:30:02",
      "activitiesCount": 4,
      "summary": "إنشاء 1 Order + إنشاء 2 OrderItem + تحديث 1 Order",
      "activities": [...]
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 1,
      "last_page": 1
    }
  }
}
```

---

## 🔍 اختبار 3: عرض Batch معين

### الخطوة 1: نسخ batch_uuid من الاختبار السابق
```bash
GET http://127.0.0.1:8000/api/v1/admin/batched-activities/9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b
Authorization: Bearer YOUR_TOKEN_HERE
```

### النتيجة المتوقعة
```json
{
  "success": true,
  "message": "Batch details retrieved successfully",
  "data": {
    "batchUuid": "9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b",
    "userName": "Admin User",
    "activitiesCount": 4,
    "summary": "إنشاء 1 Order + إنشاء 2 OrderItem + تحديث 1 Order",
    "activities": [
      {
        "id": 1,
        "logName": "Order",
        "event": "created",
        "description": "Order created",
        "subjectType": "Order",
        "subjectId": 1,
        "properties": {
          "type": "created",
          "data": {
            "name": "Test Order #1",
            "type": "internal",
            "price": 0
          }
        },
        "createdAt": "2026-02-10 14:30:00"
      },
      {
        "id": 2,
        "logName": "OrderItem",
        "event": "created",
        "description": "OrderItem created",
        "subjectType": "OrderItem",
        "subjectId": 1,
        "properties": {
          "type": "created",
          "data": {
            "order_id": 1,
            "product_id": 1,
            "qty": 2,
            "price": 50
          }
        },
        "createdAt": "2026-02-10 14:30:01"
      }
    ]
  }
}
```

---

## 🔍 اختبار 4: عرض Batches لـ Order معين

### الخطوة 1: استخدام Order ID من الاختبار الأول
```bash
GET http://127.0.0.1:8000/api/v1/admin/batched-activities/order/1
Authorization: Bearer YOUR_TOKEN_HERE
```

### النتيجة المتوقعة
```json
{
  "success": true,
  "message": "Order batches retrieved successfully",
  "data": [
    {
      "batchUuid": "...",
      "summary": "إنشاء 1 Order + إنشاء 2 OrderItem + تحديث 1 Order",
      "activitiesCount": 4,
      "startedAt": "2026-02-10 14:30:00"
    }
  ]
}
```

---

## 🔍 اختبار 5: تحديث Order

### الخطوة 1: تحديث Order موجود
```bash
PUT http://127.0.0.1:8000/api/v1/admin/orders/1
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json

{
  "name": "Updated Order #1",
  "isPaid": true,
  "status": "completed",
  "orderItems": [
    {
      "orderItemId": 1,
      "qty": 5,
      "actionStatus": "update"
    },
    {
      "orderItemId": 2,
      "actionStatus": "delete"
    },
    {
      "productId": 3,
      "qty": 1,
      "actionStatus": "create"
    }
  ]
}
```

### الخطوة 2: التحقق من Batch جديد
```bash
GET http://127.0.0.1:8000/api/v1/admin/batched-activities/order/1
```

### النتيجة المتوقعة
```json
{
  "success": true,
  "data": [
    {
      "batchUuid": "...",
      "summary": "إنشاء 1 Order + إنشاء 2 OrderItem + تحديث 1 Order",
      "activitiesCount": 4,
      "startedAt": "2026-02-10 14:30:00"
    },
    {
      "batchUuid": "...",
      "summary": "تحديث 2 Order + تحديث 1 OrderItem + حذف 1 OrderItem + إنشاء 1 OrderItem",
      "activitiesCount": 5,
      "startedAt": "2026-02-10 15:45:00"
    }
  ]
}
```

✅ **النجاح**: تم إنشاء batch جديد للتحديث

---

## 🔍 اختبار 6: الإحصائيات

### الخطوة 1: عرض إحصائيات الـ Batches
```bash
GET http://127.0.0.1:8000/api/v1/admin/batched-activities/statistics/summary
Authorization: Bearer YOUR_TOKEN_HERE
```

### النتيجة المتوقعة
```json
{
  "success": true,
  "message": "Batch statistics retrieved successfully",
  "data": {
    "totalBatches": 2,
    "totalActivities": 9,
    "averageActivitiesPerBatch": 4.5,
    "batchesByLogName": {
      "Order": 2,
      "OrderItem": 2
    },
    "batchesByEvent": {
      "created": 4,
      "updated": 3,
      "deleted": 1
    }
  }
}
```

---

## 🔍 اختبار 7: حذف Order

### الخطوة 1: حذف Order
```bash
DELETE http://127.0.0.1:8000/api/v1/admin/orders/1
Authorization: Bearer YOUR_TOKEN_HERE
```

### الخطوة 2: التحقق من Batch الحذف
```bash
GET http://127.0.0.1:8000/api/v1/admin/batched-activities/order/1
```

### النتيجة المتوقعة
```json
{
  "success": true,
  "data": [
    {
      "summary": "إنشاء 1 Order + إنشاء 2 OrderItem + تحديث 1 Order"
    },
    {
      "summary": "تحديث 2 Order + تحديث 1 OrderItem + حذف 1 OrderItem + إنشاء 1 OrderItem"
    },
    {
      "summary": "حذف 1 Order + حذف 2 OrderItem",
      "activitiesCount": 3,
      "startedAt": "2026-02-10 16:00:00"
    }
  ]
}
```

✅ **النجاح**: تم تسجيل الحذف في batch منفصل

---

## 📝 Checklist الاختبار

- [ ] إنشاء Order جديد
- [ ] التحقق من batch_uuid في قاعدة البيانات
- [ ] عرض جميع الـ Batches عبر API
- [ ] عرض Batch معين
- [ ] عرض Batches لـ Order معين
- [ ] تحديث Order والتحقق من batch جديد
- [ ] عرض الإحصائيات
- [ ] حذف Order والتحقق من batch الحذف

---

## ⚠️ استكشاف الأخطاء

### المشكلة: batch_uuid = NULL
**الحل:** تأكد من:
1. استخدام `LogBatch::startBatch()` و `LogBatch::endBatch()`
2. عدم وجود أخطاء في الكود تمنع تنفيذ `endBatch()`

### المشكلة: Route not found
**الحل:** تأكد من إضافة المسارات إلى `routes/api.php`

### المشكلة: Unauthorized
**الحل:** تأكد من:
1. إرسال Bearer token صحيح
2. المستخدم مصادق عليه

---

## ✅ النجاح

إذا نجحت جميع الاختبارات، فقد تم تطبيق LogBatch بنجاح! 🎉

**الخطوة التالية:** استخدم نفس النمط في Services أخرى (BookedDevice, SessionDevice, Daily, إلخ)
