<?php

namespace App\Http\Resources\Timer\SessionDevice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Timer\BookedDevice\BookedDeviceEditResource;

class SessionDeviceResource extends JsonResource
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
            'sessionName'=>$this->name=='individual'?'--':$this->name,
            'sessionType'=>$this->type,
            'createdAt'=>$this->created_at?Carbon::parse($this->created_at)->format('Y-m-d'):"",
            'totalPriceSession'=>$this->bookedDevices->sum('period_cost'),
            'totalOrderPrice'=>$this->bookedDevices->sum(function ($bookedDevice) {
                return $bookedDevice->orders->sum('price') ?? 0;
            }),
            //BookedDeviceResource
            'bookedDevices'=>BookedDeviceEditResource::collection($this->bookedDevices),
        ];
    }
}
