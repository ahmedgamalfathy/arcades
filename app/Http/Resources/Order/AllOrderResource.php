<?php

namespace App\Http\Resources\Order;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Order\OrderItem\OrderItemResource;

class AllOrderResource  extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'orderId' => $this->id,
            'orderNumber' => $this->number,
            'name' => $this->name??"",
            'price' => $this->price,
            'bookedDeviceId'=>$this->booked_device_id??"",
            'orderItems'=> OrderItemResource::collection($this->items),
            // 'totalOrderItems'=>$this->items->count(),
            // 'date' =>Carbon::parse($this->created_at)->format('d/m/Y')
        ];
    }
}
