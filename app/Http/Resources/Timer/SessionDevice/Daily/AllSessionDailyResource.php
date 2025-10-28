<?php

namespace App\Http\Resources\Timer\SessionDevice\Daily;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Timer\BookedDevice\BookedDevcieResource;

class AllSessionDailyResource extends JsonResource
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
            'sessioName'=> $this->name == "individual" ? $this->bookedDevices->first()->device->name : $this->name,
            'totalPriceSession'=>$this->bookedDevices->sum('period_cost'),
        ];
    }
}
