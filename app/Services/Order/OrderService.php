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
        $totalPrice = 0;
       //name , type , price
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
     return $order;

    }
    public function updateOrder(int $id, array $data)
    {
        $order = Order::find($id);
        if(!$order){
            throw new ModelNotFoundException();
        }
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

        return $order;
    }

    public function deleteOrder(int $id){
            $order = Order::find($id);
            if(!$order){
                throw new ModelNotFoundException();
            }
            $order->delete();
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
