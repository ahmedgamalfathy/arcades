<?php

namespace App\Http\Resources\Order\Daily;

use App\Http\Resources\Order\OrderItem\Daily\OrderItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllOrderDailyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'orderItems' => OrderItemResource::collection($this->items),
        ];
    }
}
