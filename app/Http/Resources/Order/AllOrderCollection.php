<?php

namespace App\Http\Resources\Order;

use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllOrderCollection extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */


    public function toArray(Request $request): array
    {
         $total = Order::count();
        return [
            'orders' => AllOrderResource::collection($this->resource->items()),
            'count'=>$total,
            'sum' =>$this->resource->sum('price'),
            'perPage' => $this->resource->count(),
            'nextPageUrl'  => $this->resource->nextPageUrl(),
            'prevPageUrl'  => $this->resource->previousPageUrl(),
        ];

    }
}
