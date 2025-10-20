<?php

namespace App\Http\Resources\Timer\pausedTime;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PasudeTimeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'bookedDeviceId'=>$this->booked_device_id,
            'pausedAt'=>$this->paused_at,
            'resumedAt'=>$this->resumed_at,
            'pausedSeconds'=>$this->paused_seconds,
        ];
    }
}
