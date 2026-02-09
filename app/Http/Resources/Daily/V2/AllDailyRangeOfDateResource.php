<?php

namespace App\Http\Resources\Daily\V2;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllDailyRangeOfDateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
            $groupedByDate = collect($this->resource->items())->groupBy(function($daily) {
            return Carbon::parse($daily->start_date_time)->format('Y-m-d');
        });

        $data = $groupedByDate->map(function($dailies, $date) {
            return [
                'date' => Carbon::parse($date)->format('d/m/Y'),
                'dailies' => DailyRangeOfDateResource::collection($dailies),
            ];
        })->values();
        return [
            'data' =>$data,
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
