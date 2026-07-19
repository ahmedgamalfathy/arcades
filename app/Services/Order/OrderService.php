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
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Services\Order\Concerns\ManagesOrderLifecycle;

class OrderService
{
    use ManagesOrderLifecycle;

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
        return DB::transaction(function () use ($data) {
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

        $order->load('items.product'); // طھط­ظ…ظٹظ„ ط§ظ„ط¹ظ„ط§ظ‚ط§طھ

        // Generate timer tracking keys if order is related to a booked device
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
                ...$timerTrackingProperties // Add timer tracking properties if available
            ])
            ->tap(function ($activity) use ($order) {
                $activity->daily_id = $order->daily_id;
            })
            ->log('Order created');

        LogBatch::endBatch();

        return $order;
        });

    }
    public function updateOrder(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
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

        $order->load('items.product'); // طھط­ظ…ظٹظ„ ط§ظ„ط¹ظ„ط§ظ‚ط§طھ

        // Generate timer tracking keys if order is related to a booked device
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
                ...$timerTrackingProperties // Add timer tracking properties if available
            ])
            ->tap(function ($activity) use ($order) {
                $activity->daily_id = $order->daily_id;
            })
            ->log('Order updated');

        LogBatch::endBatch();

        return $order;
        });
    }

}
