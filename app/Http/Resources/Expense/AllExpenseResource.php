<?php

namespace App\Http\Resources\Expense;

use Illuminate\Http\Request;
use App\Models\Expense\Expense;
use Illuminate\Http\Resources\Json\JsonResource;

class AllExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       $total = Expense::count();
        return [
            'expenses' => ExpenseResource::collection($this->resource->items()),
            'count'=>$this->resource->count()??0,
            'total'=>$this->resource->sum('price')??0,
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
