<?php

namespace App\Http\Resources\Timer\BookedDevice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Models\Setting\Param\Param;
class AllBookedDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    { 
        $statuParam = Param::select('type')->where('parameter_order', 1)->first()->type;
        if (!$this->end_date_time) {
            // لو end_date_time فاضي
            $statusParam = 'normal';
            $remainingMinutes = 0;
        } else {
            $remainingMinutes = Carbon::now()->diffInMinutes(Carbon::parse($this->end_date_time), false);
            if ($remainingMinutes < 0) {
                $statusParam = 'danger';
            } elseif ($remainingMinutes < $statuParam) {
                $statusParam = 'warning';
            } else {
                $statusParam = 'normal';
            }
        }
        return [//sessionDevice,deviceType,deviceTime,device
            'bookedDeviceId' => $this->id,
            'statusParam' => $statusParam,
            'device'=>[
                'deviceTypeName' => $this->deviceType->name,
                'deviceTimeName' => $this->deviceTime->name,
                'deviceName' => $this->device->name,
                'path'=>$this->device->media->path??"",
            ],
            'startDateTime' => $this->start_date_time ? Carbon::parse($this->start_date_time)->format('H:i') : "",
            'endDateTime' => $this->end_date_time ? Carbon::parse($this->end_date_time)->format('H:i') : "",
            'totalhour'=> $this->end_date_time? Carbon::parse($this->start_date_time)->diffForHumans($this->end_date_time):0,
            'status' => $this->status,
        ];
    }
}
