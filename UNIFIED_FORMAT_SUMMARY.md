# Unified Format Summary - Order Activity Logging

## التنسيق الموحد

تم تطبيق تنسيق موحد لجميع الحقول في جميع الحالات بصيغة `{old, new}`:

### Create (إنشاء)
```json
{
    "eventType": "created",
    "productId": {"old": null, "new": 1},
    "productName": {"old": null, "new": "شاى"},
    "quantity": {"old": null, "new": 1},
    "price": {"old": null, "new": "10.00"},
    "total": {"old": null, "new": 10}
}
```

### Update (تعديل)
```json
{
    "eventType": "updated",
    "productId": {"old": 1, "new": 1},
    "productName": {"old": "شاى", "new": "شاى"},
    "quantity": {"old": 1, "new": 5},
    "price": {"old": "10.00", "new": "10.00"},
    "total": {"old": 10, "new": 50}
}
```

### Delete (حذف)
```json
{
    "eventType": "deleted",
    "productId": {"old": 1, "new": null},
    "productName": {"old": "شاى", "new": null},
    "quantity": {"old": 5, "new": null},
    "price": {"old": "10.00", "new": null},
    "total": {"old": 50, "new": null}
}
```

## الفوائد

✅ **تنسيق موحد**: جميع الحقول بنفس الصيغة في جميع الحالات  
✅ **سهولة المعالجة**: Frontend يمكنه معالجة جميع الحالات بنفس الطريقة  
✅ **وضوح التغييرات**: يمكن رؤية القيمة القديمة والجديدة دائماً  
✅ **دعم جميع الحالات**: Create (old=null), Update (old & new), Delete (new=null)

## التطبيق

تم تعديل الـ Resource methods:

### extractOrderItemCreated
```php
return [
    'productId' => ['old' => null, 'new' => $attributes['product_id'] ?? ''],
    'productName' => ['old' => null, 'new' => $attributes['product_name'] ?? ''],
    'quantity' => ['old' => null, 'new' => $attributes['qty'] ?? ''],
    'price' => ['old' => null, 'new' => $attributes['price'] ?? ''],
    'total' => ['old' => null, 'new' => $attributes['total'] ?? ...],
];
```

### extractOrderItemUpdated
```php
return [
    'productId' => ['old' => $old['product_id'] ?? null, 'new' => $attributes['product_id'] ?? null],
    'productName' => ['old' => $old['product_name'] ?? null, 'new' => $attributes['product_name'] ?? null],
    'quantity' => ['old' => $old['qty'] ?? null, 'new' => $attributes['qty'] ?? null],
    'price' => ['old' => $old['price'] ?? null, 'new' => $attributes['price'] ?? null],
    'total' => ['old' => ..., 'new' => ...],
];
```

### extractOrderItemDeleted
```php
return [
    'productId' => ['old' => $attributes['product_id'] ?? '', 'new' => null],
    'productName' => ['old' => $attributes['product_name'] ?? '', 'new' => null],
    'quantity' => ['old' => $attributes['qty'] ?? '', 'new' => null],
    'price' => ['old' => $attributes['price'] ?? '', 'new' => null],
    'total' => ['old' => ..., 'new' => null],
];
```

## أمثلة من الاختبارات الفعلية

### Activity 13 - Create Order
```json
{
    "activityLogId": 13,
    "eventType": "created",
    "children": [
        {
            "eventType": "created",
            "productName": {"old": null, "new": "شاى"},
            "quantity": {"old": null, "new": 1},
            "price": {"old": null, "new": "10.00"},
            "total": {"old": null, "new": 10}
        }
    ]
}
```

### Activity 17 - Add Item
```json
{
    "activityLogId": 17,
    "eventType": "updated",
    "details": {"price": {"old": "10.00", "new": "652.00"}},
    "children": [
        {
            "eventType": "created",
            "productName": {"old": null, "new": "fwe"},
            "quantity": {"old": null, "new": 2},
            "price": {"old": null, "new": "321.00"},
            "total": {"old": null, "new": 642}
        }
    ]
}
```

### Activity 18 - Modify Item
```json
{
    "activityLogId": 18,
    "eventType": "updated",
    "details": {"price": {"old": "652.00", "new": "692.00"}},
    "children": [
        {
            "eventType": "updated",
            "productName": {"old": "شاى", "new": "شاى"},
            "quantity": {"old": 1, "new": 5},
            "price": {"old": "10.00", "new": "10.00"},
            "total": {"old": 10, "new": 50}
        }
    ]
}
```

### Activity 19 - Delete Item
```json
{
    "activityLogId": 19,
    "eventType": "updated",
    "details": {"price": {"old": "692.00", "new": "642.00"}},
    "children": [
        {
            "eventType": "deleted",
            "productName": {"old": "شاى", "new": null},
            "quantity": {"old": 5, "new": null},
            "price": {"old": "10.00", "new": null},
            "total": {"old": 50, "new": null}
        }
    ]
}
```

## الملفات المعدلة

**app/Http/Resources/ActivityLog/Test/AllDailyActivityResource.php**
- `extractOrderItemCreated()`: جميع الحقول بصيغة `{old: null, new: value}`
- `extractOrderItemUpdated()`: جميع الحقول بصيغة `{old: oldValue, new: newValue}`
- `extractOrderItemDeleted()`: جميع الحقول بصيغة `{old: value, new: null}`

## النتيجة النهائية

النظام الآن يوفر:
- ✅ تنسيق موحد وواضح
- ✅ سهولة في المعالجة من Frontend
- ✅ تتبع كامل لجميع التغييرات
- ✅ دعم جميع السيناريوهات (Create, Update, Delete)
- ✅ عرض فقط التغييرات الفعلية (Items لم تتغير لا تظهر)

النظام جاهز تماماً للإنتاج! 🎉
