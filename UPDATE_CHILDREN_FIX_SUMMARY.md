# Update Children Fix Summary

## المشكلة
عند تحديث Order، كانت الأطفال (OrderItems) تظهر في الـ API لكن بدون تفاصيل التغييرات:

```json
{
    "children": [
        {
            "modelName": "OrderItem",
            "eventType": "updated"
        },
        {
            "modelName": "OrderItem",
            "eventType": "updated"
        }
    ]
}
```

## السبب
عند التحديث، الـ Controller كان يحول الأطفال من `properties['children']` لكن:
1. لم يكن يربط القيم القديمة (`old['items']`) مع القيم الجديدة
2. لم يكن يميز بين Items تم تعديلها و Items جديدة تم إضافتها

## الحل

### 1. تعديل Controller لربط القيم القديمة والجديدة

في `DailyActivityController::groupParentChildActivities()`:

```php
if ($activity->event === 'updated') {
    // For updates, we need to compare old and new values
    $oldItems = $activity->properties['old']['items'] ?? [];
    
    // Create a map of old items by ID for quick lookup
    $oldItemsMap = collect($oldItems)->keyBy('id');
    
    $activity->children = collect($propertiesChildren)->map(function($childData) use ($activity, $oldItemsMap) {
        $itemId = $childData['id'] ?? null;
        $oldItem = $oldItemsMap->get($itemId);
        
        // Determine the actual event for this child
        $childEvent = $activity->event;
        if (!$oldItem) {
            // This is a new item added during update
            $childEvent = 'created';
        }
        
        $properties = ['attributes' => $childData];
        
        // If we have old data, add it for comparison
        if ($oldItem) {
            $properties['old'] = $oldItem;
        }
        
        return (object)[
            'log_name' => 'OrderItem',
            'event' => $childEvent,
            'properties' => $properties
        ];
    })->all();
}
```

**الفوائد:**
- يربط كل Item جديد مع القيم القديمة (إذا وجدت)
- يحدد الـ event الصحيح: `created` للـ Items الجديدة، `updated` للـ Items الموجودة
- يمرر القيم القديمة للـ Resource لعرض التغييرات

### 2. تحسين Resource لعرض التغييرات

في `AllDailyActivityResource::extractOrderItemUpdated()`:

```php
private function extractOrderItemUpdated($properties): array
{
    $attributes = $properties['attributes'] ?? [];
    $old = $properties['old'] ?? [];
    
    $props = [];
    
    // Important fields to track
    $importantFields = ['product_id', 'product_name', 'qty', 'price', 'total'];
    
    foreach ($importantFields as $field) {
        if (array_key_exists($field, $old) && 
            array_key_exists($field, $attributes) && 
            $old[$field] != $attributes[$field]) {
            
            $props[$field] = [
                'old' => $old[$field],
                'new' => $attributes[$field]
            ];
        }
    }
    
    // If no changes detected, show current values
    if (empty($props)) {
        return [
            'productId' => $attributes['product_id'] ?? '',
            'productName' => $attributes['product_name'] ?? '',
            'quantity' => $attributes['qty'] ?? '',
            'price' => $attributes['price'] ?? '',
            'total' => $attributes['total'] ?? '',
        ];
    }
    
    return $props;
}
```

**الفوائد:**
- يقارن الحقول المهمة فقط
- إذا كان هناك تغييرات، يعرضها بصيغة `{old, new}`
- إذا لم يكن هناك تغييرات، يعرض القيم الحالية

## النتيجة

### مثال 1: Order Update مع Item جديد

**Database:**
```json
{
    "old": {
        "items": [
            {"id": 8, "product_id": 1, "qty": 4, "price": "10.00"}
        ]
    },
    "children": [
        {"id": 8, "product_id": 1, "qty": 4, "price": "10.00", "total": 40},
        {"id": 12, "product_id": 2, "qty": 1, "price": "321.00", "total": 321}
    ]
}
```

**API Response:**
```json
{
    "activityLogId": 11,
    "eventType": "updated",
    "details": {
        "price": {
            "old": "40.00",
            "new": "361.00"
        }
    },
    "children": [
        {
            "modelName": "OrderItem",
            "eventType": "updated",
            "productId": 1,
            "productName": "شاى",
            "quantity": 4,
            "price": "10.00",
            "total": 40
        },
        {
            "modelName": "OrderItem",
            "eventType": "created",
            "productId": 2,
            "productName": "fwe",
            "quantity": 1,
            "price": "321.00",
            "total": 321
        }
    ]
}
```

### مثال 2: Order Update مع تغيير في Item

إذا تم تغيير qty من 4 إلى 5:

```json
{
    "modelName": "OrderItem",
    "eventType": "updated",
    "qty": {
        "old": 4,
        "new": 5
    },
    "price": {
        "old": "10.00",
        "new": "10.00"
    },
    "total": {
        "old": 40,
        "new": 50
    }
}
```

## الملفات المعدلة

1. **app/Http/Controllers/API/V1/Dashboard/Daily/DailyActivityController.php**
   - تعديل `groupParentChildActivities()` لربط القيم القديمة والجديدة
   - تحديد الـ event الصحيح لكل child

2. **app/Http/Resources/ActivityLog/Test/AllDailyActivityResource.php**
   - تحسين `extractOrderItemUpdated()` لعرض التغييرات بشكل واضح

## الفوائد

✅ **يعرض التغييرات الفعلية** في كل OrderItem  
✅ **يميز بين Items محدثة وجديدة** من خلال eventType  
✅ **متوافق مع النظام القديم** (legacy system)  
✅ **يعرض القيم الحالية** إذا لم يكن هناك تغييرات  
✅ **يدعم جميع أنواع التغييرات**: qty, price, product_id, etc.

## الاختبار

تم الاختبار مع Activity ID 11:
- ✅ Item 8 (لم يتغير): يعرض القيم الحالية مع `eventType: "updated"`
- ✅ Item 12 (جديد): يعرض القيم مع `eventType: "created"`
- ✅ Order details: يعرض تغيير السعر من 40.00 إلى 361.00
