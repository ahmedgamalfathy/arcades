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
            'count'=>$total,
            'perPage' => $this->resource->count(),
            'nextPageUrl'  => $this->resource->nextPageUrl(),
            'prevPageUrl'  => $this->resource->previousPageUrl(),
        ];
    }
}
