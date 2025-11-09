<?php

namespace App\Http\Resources\Timer\BookedDevice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Order\AllOrderResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use Carbon\CarbonInterface;
class BookedDeviceEditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bookedDeviceChangeTimes = BookedDevice::where('session_device_id',$this->session_device_id)->where('device_id',$this->device_id)->where('device_type_id',$this->device_type_id)->get(); 
        if (count($bookedDeviceChangeTimes )>1) {
            $totalBookedDevice=$bookedDeviceChangeTimes->sum('period_cost');
            $bookedDeviceChangeTimes = ChangeTimeDeviceResource::collection($bookedDeviceChangeTimes);
        }else{
            $bookedDeviceChangeTimes = [];
            $totalBookedDevice=0;
        }
        return [//sessionDevice,deviceType,deviceTime,device
            'bookedDeviceId' => $this->id,
            'deviceTypeId' => $this->device_type_id,
            'deviceTimeId' => $this->device_time_id,
            'deviceId' => $this->device_id,
            'device'=>[
                'deviceTypeName' => $this->deviceType->name,
                'deviceTimeName' => $this->deviceTime->name,
                'deviceName' => $this->device->name,
                'path'=>$this->device->media->path??"",
            ],
            'sessionDevice'=>[
                'sessionDeviceId'=>$this->session_device_id??"",
                'name'=>$this->sessionDevice->name=="individual"?"--":$this->sessionDevice->name,
                'startDateTime' => $this->start_date_time ? Carbon::parse($this->start_date_time)->format('H:i') : "",
                'endDateTime' => $this->end_date_time ? Carbon::parse($this->end_date_time)->format('H:i') : "",
                'createdAt'=>Carbon::parse($this->created_at)->format('Y-m-d'),
                'status' => $this->status,
            ],
            'bookedDeviceChangeTimes'=>$bookedDeviceChangeTimes,
            'totalHour' => $this->calculateTotalHour($this->start_date_time, $this->end_date_time),
            'currentTime' => $this->formatDuration($this->start_date_time, $this->end_date_time ?: Carbon::now()),
            'orders'=>$this->orders?AllOrderResource::collection($this->orders):"",
            'totalOrderPrice'=>$this->orders->sum('price'),
            'totalBookedDevicePrice'=>$totalBookedDevice,
            'totalPrice'=>$this->orders->sum('price') + $totalBookedDevice,
        ];
    }
         private function calculateTotalHour($startTime, $endTime)
    {
        if (!$endTime || $endTime->isFuture()) {
            return $startTime->diffForHumans(Carbon::now(), [
            'parts' => 2,
            'syntax' => CarbonInterface::DIFF_ABSOLUTE
            ]);
        }
        return $startTime->diffForHumans($endTime, [
            'parts' => 2,
            'syntax' => CarbonInterface::DIFF_ABSOLUTE
         ]);
    }
    private function formatDuration($startTime, $endTime)
    {
        $start = Carbon::parse($startTime)->utc();
        $end = Carbon::parse($endTime)->utc();
        $diff = $start->diff($end);
        $totalHours = ($diff->days * 24) + $diff->h;
        return sprintf('%02d:%02d:%02d', $totalHours, $diff->i, $diff->s);
    }
}
