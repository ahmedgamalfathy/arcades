<?php

namespace App\Http\Resources\Order\OrderItem\Daily;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductMedia\ProductMediaResouce;
use App\Models\Media\Media;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'price' => $this->price,
            'qty'=>$this->qty,
            'productName'=>$this->product->name,
        ];

    }
}
