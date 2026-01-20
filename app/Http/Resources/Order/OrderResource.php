<?php

namespace App\Http\Resources\Order;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Order\OrderItem\OrderItemResource;

class OrderResource extends JsonResource
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
            'isPaid' => $this->is_paid,
            'status' => $this->status,
            'bookedDeviceId'=>$this->booked_device_id??"",
            'orderNumber' => $this->number,
            'name' => $this->name??"",
            'price' => $this->price,
            'date' =>Carbon::parse($this->created_at)->format('d/m/Y'),
            'time' =>Carbon::parse($this->created_at)->format('H:i'),
            // 'totalOrderItems'=>$this->items->count(),
            'orderItems'=> OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
