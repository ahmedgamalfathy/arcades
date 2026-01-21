<?php

namespace App\Http\Resources\Timer\SessionDevice\Daily;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Timer\BookedDevice\BookedDevcieResource;

class AllSessionIncomeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sessionName = $this->name;
        if ($this->name == "individual") {
        $firstDevice = $this->bookedDevices->first();
        $sessionName = $firstDevice?->device?->name ?? 'Unknown Device';
        }
        return [
            'sessionId'=>$this->id,
            'sessioName'=> $sessionName,
            'totalPriceSession'=>$this->bookedDevices->sum('actual_paid_amount')??0,
            'createdAt' =>Carbon::parse($this->created_at)->format('d/m/Y'),
            'timeCreated' =>Carbon::parse($this->created_at)->format('H:i'),
            'periodCost'=>$this->bookedDevices->sum('actual_paid_amount')??"",
        ];
    }
}
