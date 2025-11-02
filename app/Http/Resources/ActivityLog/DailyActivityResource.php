<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\User;
class DailyActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $props = $this->properties['attributes'] ?? [];
        return [
            'modelName' => $this->log_name,
            'event' => $this->event,
            'properties' =>[
                'startDate'=>$props['start_date_time']??"",
                'endDate'=>$props['end_date_time']??"",
            ],
            'message' => $this->description??"",
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'userName' => $this->causer_id?User::find($this->causer_id)->name:"",
        ];
    }
}
