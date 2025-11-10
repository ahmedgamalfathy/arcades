<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Media\Media;
class ProductResource extends JsonResource
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
            'productId' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            // 'status' => $this->status,
            'path' => $this->media->path?? $media->path??"",
        ];
    }
}
