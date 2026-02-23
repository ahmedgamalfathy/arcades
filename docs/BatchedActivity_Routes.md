# مسارات Batched Activity Log

## إضافة المسارات إلى routes/api.php

أضف هذه المسارات إلى ملف `routes/api.php` داخل مجموعة `admin`:

```php
use App\Http\Controllers\API\V1\Dashboard\ActivityLog\BatchedActivityController;

Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    
    // ... المسارات الموجودة ...
    
    // Batched Activity Log Routes
    Route::prefix('batched-activities')->group(function () {
        // عرض جميع الـ batches
        Route::get('/', [BatchedActivityController::class, 'index']);
        
        // عرض تفاصيل batch معين
        Route::get('/{batchUuid}', [BatchedActivityController::class, 'show']);
        
        // عرض batches خاصة بـ Order
        Route::get('/order/{orderId}', [BatchedActivityController::class, 'orderBatches']);
        
        // عرض batches خاصة بـ Daily
        Route::get('/daily/{dailyId}', [BatchedActivityController::class, 'dailyBatches']);
        
        // إحصائيات الـ batches
        Route::get('/statistics/summary', [BatchedActivityController::class, 'statistics']);
    });
});
```

## أمثلة على الاستخدام

### 1. عرض جميع الـ Batches
```http
GET /api/v1/admin/batched-activities?perPage=15&dailyId=5
```

**Response:**
```json
{
  "success": true,
  "message": "Batched activities retrieved successfully",
  "data": [
    {
      "batchUuid": "9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b",
      "userName": "Ahmed",
      "userId": 1,
      "dailyId": 5,
      "startedAt": "2026-02-10 14:30:00",
      "endedAt": "2026-02-10 14:30:02",
      "activitiesCount": 5,
      "summary": "إنشاء 1 Order + إنشاء 3 OrderItem + تحديث 1 Order",
      "activities": [
        {
          "id": 123,
          "logName": "Order",
          "event": "created",
          "description": "Order created",
          "subjectType": "Order",
          "subjectId": 45,
          "properties": {
            "type": "created",
            "data": {
              "name": "Order #45",
              "type": "internal",
              "price": 0
            }
          },
          "createdAt": "2026-02-10 14:30:00"
        },
        {
          "id": 124,
          "logName": "OrderItem",
          "event": "created",
          "description": "OrderItem created",
          "subjectType": "OrderItem",
          "subjectId": 101,
          "properties": {
            "type": "created",
            "data": {
              "order_id": 45,
              "product_id": 10,
              "qty": 2,
              "price": 50
            }
          },
          "createdAt": "2026-02-10 14:30:01"
        }
      ]
    }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 50,
      "last_page": 4
    }
  }
}
```

### 2. عرض تفاصيل Batch معين
```http
GET /api/v1/admin/batched-activities/9d8e7f6a-5b4c-3d2e-1a0b-9c8d7e6f5a4b
```

### 3. عرض Batches خاصة بـ Order
```http
GET /api/v1/admin/batched-activities/order/45
```

**Response:**
```json
{
  "success": true,
  "message": "Order batches retrieved successfully",
  "data": [
    {
      "batchUuid": "...",
      "summary": "إنشاء Order مع 3 OrderItems",
      "activitiesCount": 5,
      "startedAt": "2026-02-10 14:30:00"
    },
    {
      "batchUuid": "...",
      "summary": "تحديث Order + حذف 1 OrderItem + إنشاء 2 OrderItem",
      "activitiesCount": 4,
      "startedAt": "2026-02-10 15:45:00"
    }
  ]
}
```

### 4. عرض Batches خاصة بـ Daily
```http
GET /api/v1/admin/batched-activities/daily/5
```

### 5. إحصائيات الـ Batches
```http
GET /api/v1/admin/batched-activities/statistics/summary?dailyId=5
```

**Response:**
```json
{
  "success": true,
  "message": "Batch statistics retrieved successfully",
  "data": {
    "totalBatches": 25,
    "totalActivities": 150,
    "averageActivitiesPerBatch": 6,
    "batchesByLogName": {
      "Order": 25,
      "OrderItem": 25,
      "BookedDevice": 10
    },
    "batchesByEvent": {
      "created": 40,
      "updated": 30,
      "deleted": 5
    }
  }
}
```

## Query Parameters

### للمسار الرئيسي (index)
- `perPage` (optional): عدد النتائج في الصفحة (default: 15)
- `dailyId` (optional): تصفية حسب daily_id

### للإحصائيات (statistics)
- `dailyId` (optional): تصفية حسب daily_id

## ملاحظات

1. جميع المسارات تتطلب مصادقة (`auth:sanctum`)
2. يمكن إضافة middleware للصلاحيات حسب الحاجة
3. الـ Response يتبع نفس بنية ApiResponse المستخدمة في المشروع
4. يمكن توسيع الـ filters لإضافة تصفيات أخرى (تاريخ، مستخدم، إلخ)
