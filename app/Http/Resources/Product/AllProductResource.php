<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'products' => ProductResource::collection($this->resource->items()),
            'perPage'      => $this->resource->count(),
            'nextPageUrl'  => $this->extractCursor($this->resource->nextPageUrl()),
            'prevPageUrl'  => $this->extractCursor($this->resource->previousPageUrl()),
        ];
    }
    private function extractCursor(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        return $params['cursor'] ?? null;
    }
}
