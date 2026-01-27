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
        return [
            'orders' => AllOrderResource::collection($this->resource->items()),
            // 'count'=>$this->resource->count(),
            // 'sum' =>round($this->resource->sum('price'),2),
            'count' => $this->resource->total_count ?? 0,
            'sum' => $this->resource->total_sum ?? 0,
            'perPage' => $this->resource->count(),
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
