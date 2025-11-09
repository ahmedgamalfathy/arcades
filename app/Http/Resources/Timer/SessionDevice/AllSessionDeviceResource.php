<?php

namespace App\Http\Resources\Timer\SessionDevice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Timer\BookedDevice\AllBookedDeviceResource;

class AllSessionDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sessionId'=>$this->id,
            'sessionName'=>$this->name == 'individual' ?'--': $this->name,
            'bookedDevices'=>AllBookedDeviceResource::collection($this->bookedDevices),
        ];
    }
}
