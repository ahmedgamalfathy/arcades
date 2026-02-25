<?php
namespace App\Services\Order;

use App\Models\Order\Order;
use Illuminate\Http\Request;
use App\Enums\Order\OrderTypeEnum;
use App\Filters\Order\FilterOrder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Enums\Order\OrderStatus;
use Spatie\Activitylog\Facades\LogBatch;

class OrderService
{
    protected $orderItemService;
    public function __construct( OrderItemService $orderItemService)
    {
        $this->orderItemService = $orderItemService;
    }
    public function allOrders(Request $request){
        $perPage= $request->query('perPage',10);
        $query  = QueryBuilder::for(Order::class)
        ->allowedFilters([
           AllowedFilter::custom('search', new FilterOrder),
           AllowedFilter::exact('type', 'type'),
           AllowedFilter::exact('isPaid', 'is_paid'),
           AllowedFilter::exact('status', 'status'),
           AllowedFilter::exact('dailyId', 'daily_id'),
        ]);
        $orders=(clone $query)
        ->with(['items'])
        ->orderByDesc('created_at')
        ->cursorPaginate($perPage);
        $orders->total_count = $query->count();
        $orders->total_sum = round($query->sum('price'), 2);
        return $orders;
    }
    public function editOrder(int $id){
        $order= Order::with(['items'])->find($id);
        if(!$order){
            throw new ModelNotFoundException();
        }
        return $order;
    }

    public function createOrder(array $data){
        LogBatch::startBatch();

        $totalPrice = 0;

        if($data['type'] == OrderTypeEnum::INTERNAL->value){
            $order = Order::create([
                'name'=>$data['name']??null,
                'type' => OrderTypeEnum::from($data['type'])->value,
                'is_paid'=>$data['isPaid']??false,
                'status'=>$data['status']??OrderStatus::PENDING->value,
                'booked_device_id'=>$data['bookedDeviceId']??null,
                'daily_id'=>$data['dailyId']??null,
            ]);
        }else{
            $order = Order::create([
                'name'=>$data['name']??null,
                'type' => OrderTypeEnum::from($data['type'])->value,
                'daily_id'=>$data['dailyId']??null,
                'is_paid'=>$data['isPaid']??false,
                'status'=>$data['status']??OrderStatus::PENDING->value,
            ]);
        }

        foreach ($data['orderItems'] as $itemData) {
            $item= $this->orderItemService->createOrderItem([
                    'orderId' => $order->id,
                    ...$itemData
                ]);

            $totalPrice += $item->price * $item->qty;
        }

        $order->update([
            'price' => $totalPrice,
        ]);

        // تسجيل يدوي واحد فقط لكل الـ batch
        $order->load('items.product'); // تحميل العلاقات
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
            ])
            ->tap(function ($activity) use ($order) {
                $activity->daily_id = $order->daily_id;
            })
            ->log('Order created');

        LogBatch::endBatch();

        return $order;

    }
    public function updateOrder(int $id, array $data)
    {
        LogBatch::startBatch();

        $order = Order::find($id);
        if(!$order){
            throw new ModelNotFoundException();
        }

        $oldData = [
            'name' => $order->name,
            'is_paid' => $order->is_paid,
            'status' => $order->status,
            'price' => $order->price,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'qty' => $item->qty,
                    'price' => $item->price,
                ];
            })->toArray(),
        ];

        $order->name = $data['name']??null;
        $order->is_paid = $data['isPaid'];
        $order->status = $data['status'];
        $order->save();

        foreach ($data['orderItems'] as $itemData) {
            if ($itemData['actionStatus'] === 'update') {
                $this->orderItemService->updateOrderItem($itemData['orderItemId'], [
                    'orderId' => $order->id,
                    ...$itemData
                ]);
            }

            if ($itemData['actionStatus'] === 'delete') {
                $this->orderItemService->deleteOrderItem($itemData['orderItemId']);
            }

            if ($itemData['actionStatus'] === 'create') {
                $this->orderItemService->createOrderItem([
                    'orderId' => $order->id,
                    ...$itemData
                ]);
            }

            if ($itemData['actionStatus'] == '') {
                $this->orderItemService->editOrderItem($itemData['orderItemId']);
            }
        }

        $totalPrice = $order->items()->sum(DB::raw('price * qty'));
        $order->price = $totalPrice;
        $order->save();

        // تسجيل يدوي واحد بعد اكتمال كل شيء
        $order->load('items.product'); // تحميل العلاقات
        activity()
            ->useLog('Order')
            ->event('updated')
            ->performedOn($order)
            ->withProperties([
                'old' => $oldData,
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
            ])
            ->tap(function ($activity) use ($order) {
                $activity->daily_id = $order->daily_id;
            })
            ->log('Order updated');

        LogBatch::endBatch();

        return $order;
    }

    public function deleteOrder(int $id){
            LogBatch::startBatch();

            $order = Order::find($id);
            if(!$order){
                throw new ModelNotFoundException();
            }

            // حفظ البيانات قبل الحذف
            $order->load('items.product');

            $orderData = [
                'id' => $order->id,
                'name' => $order->name,
                'number' => $order->number,
                'price' => $order->price,
                'booked_device_id' => $order->booked_device_id,
            ];

            $itemsData = $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'total' => $item->qty * $item->price,
                ];
            })->toArray();

            $dailyId = $order->daily_id;

            $order->delete();

            // تسجيل يدوي واحد بعد الحذف
            activity()
                ->useLog('Order')
                ->event('deleted')
                ->withProperties([
                    'old' => $orderData,
                    'children' => $itemsData,
                ])
                ->tap(function ($activity) use ($dailyId) {
                    $activity->daily_id = $dailyId;
                })
                ->log('Order deleted');

            LogBatch::endBatch();
    }
    public function restoreOrder(int $id)
    {
        LogBatch::startBatch();

        $order = Order::onlyTrashed()->findOrFail($id);
        $order->restore();

        // تسجيل يدوي واحد
        $order->load('items.product'); // تحميل العلاقات
        activity()
            ->useLog('Order')
            ->event('restored')
            ->performedOn($order)
            ->withProperties([
                'attributes' => [
                    'id' => $order->id,
                    'name' => $order->name,
                    'number' => $order->number,
                    'price' => $order->price,
                    'daily_id' => $order->daily_id,
                ],
                'children' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product?->name,
                        'qty' => $item->qty,
                        'price' => $item->price,
                    ];
                })->toArray(),
            ])
            ->tap(function ($activity) use ($order) {
                $activity->daily_id = $order->daily_id;
            })
            ->log('Order restored');

        LogBatch::endBatch();

        return $order;
    }
    public function forceDeleteOrder(int $id)
    {
        LogBatch::startBatch();

        $order = Order::withTrashed()->findOrFail($id);

        // حفظ البيانات قبل الحذف النهائي
        $orderData = [
            'id' => $order->id,
            'name' => $order->name,
            'number' => $order->number,
            'price' => $order->price,
        ];

        $dailyId = $order->daily_id;

        $order->forceDelete();

        // تسجيل يدوي واحد
        activity()
            ->useLog('Order')
            ->event('deleted')
            ->withProperties([
                'old' => $orderData,
            ])
            ->tap(function ($activity) use ($dailyId) {
                $activity->daily_id = $dailyId;
            })
            ->log('Order permanently deleted');

        LogBatch::endBatch();
    }
    public function changeOrderStatus(int $id, array $data)
    {
        $order = Order::findOrFail($id);
        $order->status = $data['status'];
        $order->save();
        return $order;
    }
    public function changeOrderPaymentStatus(int $id, array $data)
    {
        $order = Order::findOrFail($id);
        $order->is_paid = $data['isPaid'];
        $order->save();
        return $order;
    }

}
