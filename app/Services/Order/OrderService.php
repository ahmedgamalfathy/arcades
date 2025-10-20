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

class OrderService
{
    protected $orderItemService;
    public function __construct( OrderItemService $orderItemService)
    {
        $this->orderItemService = $orderItemService;
    }
    public function allOrders(Request $request,int $type){
        $perPage= $request->query('perPage',10);
        $orders = QueryBuilder::for(Order::class)
        ->allowedFilters([
           AllowedFilter::custom('search', new FilterOrder),
        ])
        ->with(['items'])
        ->where('type',$type)
        ->cursorPaginate($perPage);
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
                'booked_device_id'=>$data['bookedDeviceId'],
            ]);
        }else{
            $order = Order::create([
                'name'=>$data['name']??null,
                'type' => OrderTypeEnum::from($data['type'])->value,
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

}
