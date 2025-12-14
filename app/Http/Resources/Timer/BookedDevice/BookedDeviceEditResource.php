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
    $lastBookedDevice = BookedDevice::where('session_device_id', $this->session_device_id)
    ->where('device_id', $this->device_id)
    ->where('device_type_id', $this->device_type_id)
    ->orderBy('created_at', 'desc')
    ->first();
    $bookedDeviceChangeTimes = BookedDevice::where('session_device_id', $this->session_device_id)
    ->where('device_id', $this->device_id)
    ->where('device_type_id', $this->device_type_id)
    ->whereNull("end_date_time")
    ->latest('created_at')
    ->get();

    if (count($bookedDeviceChangeTimes) >= 1) {
        // احسب الإجمالي (live للشغال + المحفوظة للمقفول)
        $totalBookedDevice = $bookedDeviceChangeTimes->sum(function($device) {
            return ($device->status != 0)
                ? $device->calculatePrice()
                : ($device->period_cost ?? 0);
        });

        // حول للـ Resource
        $bookedDeviceChangeTimes = ChangeTimeDeviceResource::collection($bookedDeviceChangeTimes);
    } else {
        $bookedDeviceChangeTimes = [];
        $totalBookedDevice = 0;
    }

    // احسب تكلفة الجهاز الحالي
    $currentDeviceCost = ($this->status != 0)
        ? $this->calculatePrice()
        : ($this->period_cost ?? 0);

    return [
        'bookedDeviceId' => $this->id,
        'deviceTypeId' => $this->device_type_id,
        'deviceTimeId' => $this->device_time_id,
        'deviceId' => $this->device_id,
        'device' => [
            'deviceTypeName' => $this->deviceType->name,
            'deviceTimeName' => $lastBookedDevice->deviceTime->name,
            'deviceName' => $this->device->name,
            'path' => $this->device->media->path ?? "",
        ],
        'sessionDevice' => [
            'sessionDeviceId' => $this->session_device_id ?? "",
            'name' => $this->sessionDevice->name == "individual" ? "--" : $this->sessionDevice->name,
            'startDateTime' => $this->start_date_time ? Carbon::parse($this->start_date_time)->format('H:i') : "",
            'endDateTime' => $this->end_date_time ? Carbon::parse($this->end_date_time)->format('H:i') : "",
            'createdAt' => Carbon::parse($this->created_at)->format('Y-m-d'),
            'status' => $this->status,
        ],
        'bookedDeviceChangeTimes' => $bookedDeviceChangeTimes, // هنا الـ Resource
        'timeRemaining' => $this->timeRemaining(),
        'totalHour' => $this->calculateTotalHour($this->start_date_time, $this->end_date_time),
        'currentTime' => $this->formatDuration($this->start_date_time, $this->end_date_time),
        'orders' => $this->orders ? AllOrderResource::collection($this->orders) : "",
        'totalOrderPrice' => $this->orders->sum('price'),
        'totalBookedDevicePrice' => $totalBookedDevice, // الإجمالي (live + محفوظ)
        'currentDevicePrice' => $currentDeviceCost, // تكلفة الجهاز الحالي
        'totalPrice' => $this->orders->sum('price') + $totalBookedDevice,
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
    private function formatDuration($startTime, $endTime = null)
    {
        $start = Carbon::parse($startTime);
        $now = Carbon::now();
        if ($endTime) {
            $end = Carbon::parse($endTime);
            $effectiveEnd = $now->lessThan($end) ? $now : $end;
        } else {
            $effectiveEnd = $now;
        }
        if ($effectiveEnd->lessThan($start)) {
            return "00:00:00";
        }
        $diff = $start->diff($effectiveEnd);
        $totalHours = ($diff->days * 24) + $diff->h;

        return sprintf('%02d:%02d:%02d', $totalHours, $diff->i, $diff->s);
    }
    private function timeRemaining()
    {
        $now = Carbon::now();
        if (!$this->end_date_time) {
            return "--";
        }
        $end = Carbon::parse($this->end_date_time);
        $effectiveEnd = $now->lessThan($end) ? $now : $end;
        if ($effectiveEnd->lessThan($this->start_date_time)) {
            return "00:00:00";
        }
        $diff = $this->start_date_time->diff($effectiveEnd);
        $totalHours = ($diff->days * 24) + $diff->h;

        return sprintf('%02d:%02d:%02d', $totalHours, $diff->i, $diff->s);
    }

    private function calculateTotalDevicesCost($devices)
    {
        if ($devices->isEmpty()) {
            return 0;
        }
        return $devices->sum(function($device) {
            return $device->status != 0
                ? $device->calculatePrice()  // لو شغال احسب live
                : ($device->period_cost ?? 0); // لو مقفول خد المحفوظ
        });
    }
    private function getCurrentDeviceCost()
    {
        return $this->status != 0
            ? $this->calculatePrice()  // لو شغال احسب live
            : ($this->period_cost ?? 0); // لو مقفول خد المحفوظ
    }
}
