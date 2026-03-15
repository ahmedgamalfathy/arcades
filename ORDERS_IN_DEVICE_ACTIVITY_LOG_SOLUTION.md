# حل إظهار الأوردرات في Activity Log الخاص بالجهاز

## المطلوب
إظهار الأوردرات (Orders) الخاصة بالجهاز في activity log الخاص بالجهاز أيضاً.

## الحل المطبق

### 1. تحديث دالة `getActivityLogToDevice`
**الملف**: `app/Services/Timer/BookedDeviceService.php`

تم إضافة الأوردرات المرتبطة بالجهاز في الاستعلام:

```php
public function getActivityLogToDevice($bookedDeviceId)
{
    // ... existing code ...

    $activities = Activity::where(function ($query) use ($timerId, $currentBookedDevice) {
        $query->whereJsonContains('properties->timer_id', $timerId)
              ->whereDate('created_at', today())
              ->where(function ($subQuery) use ($currentBookedDevice) {
                  $subQuery->where(function ($q) use ($currentBookedDevice) {
                      // Activities for current BookedDevice
                      $q->where('subject_type', BookedDevice::class)
                        ->where('subject_id', $currentBookedDevice->id);
                  })
                  ->orWhere(function ($q) use ($currentBookedDevice) {
                      // Activities for current SessionDevice
                      if ($currentBookedDevice->session_device_id) {
                          $q->where('subject_type', SessionDevice::class)
                            ->where('subject_id', $currentBookedDevice->session_device_id);
                      }
                  })
                  ->orWhere(function ($q) use ($currentBookedDevice) {
                      // ✅ NEW: Activities for Orders related to current BookedDevice
                      $q->where('subject_type', Order::class)
                        ->whereIn('subject_id', function($orderQuery) use ($currentBookedDevice) {
                            $orderQuery->select('id')
                                       ->from('orders')
                                       ->where('booked_device_id', $currentBookedDevice->id)
                                       ->whereDate('created_at', today());
                        });
                  });
              });
    })
    ->orderBy('created_at', 'desc')
    ->get();

    // ... rest of the method ...
}
```

### 2. تحديث إنشاء الأوردرات
**الملف**: `app/Services/Order/OrderService.php`

تم إضافة timer tracking للأوردرات المرتبطة بالأجهزة:

```php
public function createOrder(array $data)
{
    // ... existing order creation code ...

    // ✅ NEW: Generate timer tracking keys if order is related to a booked device
    $timerTrackingProperties = [];
    if ($order->booked_device_id) {
        $bookedDevice = BookedDevice::find($order->booked_device_id);
        if ($bookedDevice) {
            $sessionDevice = $bookedDevice->sessionDevice;
            if ($sessionDevice) {
                $dailyId = $sessionDevice->daily_id;
                $deviceStartDate = $bookedDevice->created_at->format('Y-m-d');
                $deviceSessionKey = 'device_' . $bookedDevice->device_id . '_daily_' . $dailyId . '_' . $deviceStartDate;
                $timerId = 'timer_' . $bookedDevice->device_id . '_' . $bookedDevice->created_at->timestamp;

                $timerTrackingProperties = [
                    'device_session_key' => $deviceSessionKey,
                    'timer_id' => $timerId,
                    'device_id' => $bookedDevice->device_id,
                    'related_to_device' => true,
                    'session_type' => $sessionDevice->type == 0 ? 'individual' : 'group'
                ];
            }
        }
    }

    activity()
        ->useLog('Order')
        ->event('created')
        ->performedOn($order)
        ->withProperties([
            'attributes' => [
                'id' => $order->id,
                'name' => $order->name,
                'number' => $order->number,
                'type' => $order->type,
                'price' => $order->price,
                'is_paid' => $order->is_paid,
                'status' => $order->status,
                'booked_device_id' => $order->booked_device_id,
                'daily_id' => $order->daily_id,
            ],
            'children' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'total' => $item->qty * $item->price,
                ];
            })->toArray(),
            'summary' => [
                'total_items' => $order->items->count(),
                'total_price' => $order->price,
            ],
            ...$timerTrackingProperties // ✅ Add timer tracking properties
        ])
        ->tap(function ($activity) use ($order) {
            $activity->daily_id = $order->daily_id;
        })
        ->log('Order created');

    return $order;
}
```

### 3. تحديث تعديل الأوردرات
تم تطبيق نفس المنطق على دالة `updateOrder` لضمان أن تحديثات الأوردرات تظهر أيضاً في activity log الخاص بالجهاز.

### 4. إضافة Import مطلوب
تم إضافة import للـ BookedDevice في OrderService:

```php
use App\Models\Timer\BookedDevice\BookedDevice;
```

## المميزات الجديدة

### ✅ إظهار الأوردرات في Activity Log
- الأوردرات المرتبطة بالجهاز تظهر الآن في activity log الخاص بالجهاز
- تتضمن تفاصيل كاملة عن الأوردر (الاسم، الرقم، السعر، الحالة)
- تظهر معلومات الـ items والملخص

### ✅ Timer ID متسق
- جميع الأوردرات المرتبطة بالجهاز تحمل نفس timer_id
- يربط الأوردرات بدورة حياة التايمر الكاملة
- يضمن ظهور الأوردرات مع باقي أنشطة الجهاز

### ✅ تصفية بالتاريخ
- فقط أوردرات اليوم الحالي تظهر
- لا توجد أوردرات قديمة من أيام سابقة
- متسق مع باقي نظام التصفية

### ✅ معلومات إضافية
- `related_to_device`: يحدد أن الأوردر مرتبط بجهاز
- `device_id`: معرف الجهاز المرتبط
- `session_type`: نوع الجلسة (individual/group)

## نتائج الاختبار

```
📊 Activities count: 2

📝 Activities including order:
  - Event: created
    Log Name: SessionDevice
    Description: SessionDevice - Individual time created
    Timer ID: timer_1_1773565799
    
  - Event: created
    Log Name: Order
    Description: Order created
    Timer ID: timer_1_1773565799
    Related to Device: 1
    Order Name: Test Order
    Order Number: ORD_17910326
    Order Price: 25.5

🎯 Summary:
  - Total activities: 2
  - Order activities: 1
✅ SUCCESS: Order is showing in device activity log
✅ All activities have consistent timer_id: timer_1_1773565799
```

## الأنشطة المشمولة الآن

الآن activity log للجهاز يتضمن:
- ✅ **إنشاء الجهاز**: إنشاء الجلسة والجهاز
- ✅ **Pause/Resume**: عمليات الإيقاف والاستئناف
- ✅ **Transfer**: نقل بين الجلسات
- ✅ **Finish**: إنهاء الجهاز
- ✅ **Orders**: الأوردرات المرتبطة بالجهاز ✨ **جديد**
- ✅ **Order Updates**: تحديثات الأوردرات ✨ **جديد**

## الخلاصة

تم تطبيق الحل بنجاح. الآن عندما يتم إنشاء أو تحديث أوردر مرتبط بجهاز معين، سيظهر هذا الأوردر في activity log الخاص بالجهاز مع:
- نفس timer_id للربط مع باقي أنشطة الجهاز
- تفاصيل كاملة عن الأوردر والـ items
- تصفية صحيحة للجلسة الحالية فقط
- معلومات واضحة عن الارتباط بالجهاز
