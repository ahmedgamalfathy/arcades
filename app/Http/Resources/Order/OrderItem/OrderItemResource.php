<?php

namespace App\Http\Resources\Order\OrderItem;

use App\Http\Resources\Media\AllMediaResource;
use App\Http\Resources\Media\MediaResource;
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
       $media = Media::where('category','products')->first();
        return [
            'orderItemId' => $this->id,
            'orderId' => $this->order_id,
            'price' => $this->price,
            'qty' => $this->qty,
            'totalPrice'=>round($this->price*$this->qty,2),
            'product' => [
                'productId' => $this->product_id,
                'name' => $this->product->name,
                'path'=>
                $this->product->media
                ? $this->product->media->path
                : $media?->path,
            ]
        ];

    }
}
