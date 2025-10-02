<?php

namespace App\Http\Resources\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllMaintenanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
    //    $total = Expense::count();
        return [
            'maintenaces' => MaintenanceResource::collection($this->resource->items()),
            // 'count'=>$total,
            // 'perPage' => $this->resource->count(),
            // 'nextPageUrl'  => $this->resource->nextPageUrl(),
            // 'prevPageUrl'  => $this->resource->previousPageUrl(),
        ];
    }
}
