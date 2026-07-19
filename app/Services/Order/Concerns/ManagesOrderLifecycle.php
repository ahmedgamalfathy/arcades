<?php

namespace App\Services\Order\Concerns;

use App\Models\Order\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Activitylog\Facades\LogBatch;

trait ManagesOrderLifecycle
{
    public function deleteOrder(int $id){
            LogBatch::startBatch();

            $order = Order::find($id);
            if(!$order){
                throw new ModelNotFoundException();
            }

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

        $order->load('items.product'); // طھط­ظ…ظٹظ„ ط§ظ„ط¹ظ„ط§ظ‚ط§طھ
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

        $orderData = [
            'id' => $order->id,
            'name' => $order->name,
            'number' => $order->number,
            'price' => $order->price,
        ];

        $dailyId = $order->daily_id;

        $order->forceDelete();

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
        $oldStatus = $order->status;
        $order->status = $data['status'];
        $order->save();

        // Load items with product for children
        $order->load('items.product');

        // Log the status change with items as children
        activity()
            ->useLog('Order')
            ->event('updated')
            ->performedOn($order)
            ->withProperties([
                'old' => [
                    'status' => $oldStatus,
                    'number' => '',
                    'name' => '',
                    'price' => '',
                    'booked_device_id' => $order->booked_device_id,
                ],
                'attributes' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'number' => $order->number,
                    'name' => $order->name,
                    'price' => $order->price,
                    'booked_device_id' => $order->booked_device_id,
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
            ])
            ->tap(function ($activity) use ($order) {
                $activity->daily_id = $order->daily_id;
            })
            ->log('Order status changed');

        return $order;
    }
    public function changeOrderPaymentStatus(int $id, array $data)
    {
        $order = Order::findOrFail($id);
        $oldIsPaid = $order->is_paid;
        $order->is_paid = $data['isPaid'];
        $order->save();

        // Load items with product for children
        $order->load('items.product');

        // Log the payment status change with items as children
        activity()
            ->useLog('Order')
            ->event('updated')
            ->performedOn($order)
            ->withProperties([
                'old' => [
                    'is_paid' => $oldIsPaid,
                    'number' => '',
                    'name' => '',
                    'price' => '',
                    'booked_device_id' => $order->booked_device_id,
                ],
                'attributes' => [
                    'id' => $order->id,
                    'is_paid' => $order->is_paid,
                    'number' => $order->number,
                    'name' => $order->name,
                    'price' => $order->price,
                    'booked_device_id' => $order->booked_device_id,
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
            ])
            ->tap(function ($activity) use ($order) {
                $activity->daily_id = $order->daily_id;
            })
            ->log('Order payment status changed');

        return $order;
    }

}
