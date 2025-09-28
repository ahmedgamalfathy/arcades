<?php
namespace App\Services\Order;

use App\Models\Order\OrderItem;
use App\Models\Product\Product;

class OrderItemService
{
    public function allOrderItems()
    {
        $orderItems = OrderItem::get();
        return $orderItems;
    }

    public function editOrderItem($id)
    {
        $orderItem = OrderItem::with(['order', 'product'])->find($id);
        return $orderItem;
    }
    public function createOrderItem(array $data)
    {
        $product = Product::where('id', $data['productId'])->select('price')->first();
        $orderItem = OrderItem::create([
            'order_id' => $data['orderId'],
            'product_id' => $data['productId'],
            'price' => $product->price,
            'qty' => $data['qty'],
        ]);
        return $orderItem;
    }
    public function updateOrderItem(int $id,array $data ){
        $orderItem = OrderItem::find($id);
        $product = Product::where('id', $orderItem->product_id)->select('price')->first();//order_id, product_id ,price ,qty
        $orderItem->update([
            'qty' => $data['qty'],
            'price' => $product->price,
        ]);
        return $orderItem;
    }
    public function deleteOrderItem(int $id)
    {
        $orderItem = OrderItem::find($id);
            $orderItem->delete();
    }
}
