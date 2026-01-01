<?php

namespace App\Http\Resources\Timer\BookedDevice;

use Carbon\Carbon;
use App\Models\Order\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use App\Models\Setting\Param\Param;
use App\Http\Resources\Order\AllOrderResource;
use App\Models\Timer\BookedDevice\BookedDevice;
use Illuminate\Http\Resources\Json\JsonResource;

class BookedDeviceEditResource extends JsonResource
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
        $remainingMinutes = Carbon::now()->diffInMinutes(Carbon::parse($this->end_date_time),false);
        if ($remainingMinutes < 0) {
            $statusParam = 'danger';
        } elseif ($remainingMinutes < $statuParam) {
            $statusParam = 'warning';
        } else {
            $statusParam = 'normal';
        }
    }
    $lastBookedDevice = BookedDevice::where('session_device_id', $this->session_device_id)
    ->where('device_id', $this->device_id)
    ->where('device_type_id', $this->device_type_id)
    ->orderBy('created_at', 'desc')
    ->first();
    $bookedDeviceChangeTimes = BookedDevice::where('session_device_id', $this->session_device_id)
    ->where('device_id', $this->device_id)
    ->where('device_type_id', $this->device_type_id)
    // ->whereNull("end_date_time")
    ->latest('created_at')
    ->get();

    if (count($bookedDeviceChangeTimes) >= 1) {
        // احسب الإجمالي (live للشغال + المحفوظة للمقفول)
        $totalBookedDevice = $bookedDeviceChangeTimes->sum(function($device) {
            return $device->current_device_cost;
        });

        // حول للـ Resource
        $bookedDeviceChangeTimes = ChangeTimeDeviceResource::collection($bookedDeviceChangeTimes);
        $bookedDeviceIds = $bookedDeviceChangeTimes->pluck('id');
        $deviceOrders = Order::whereIn('booked_device_id', $bookedDeviceIds)->get();

    } else {
        $bookedDeviceChangeTimes = [];
        $totalBookedDevice = 0;
        $deviceOrders = collect();
    }

    // احسب تكلفة الجهاز الحالي
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
            'statusParam' => $statusParam,
        ],
        'bookedDeviceChangeTimes' => $bookedDeviceChangeTimes, // هنا الـ Resource
        'timeRemaining' => $this->formatDuration($this->start_date_time, $this->end_date_time),
        // 'totalHour' => $this->calculateTotalHour($this->start_date_time, $this->end_date_time),
        'currentTime' => $this->formatDuration($this->start_date_time, $this->end_date_time),
        // 'orders' => $this->orders ? AllOrderResource::collection($this->orders) : "",
        'orders' => AllOrderResource::collection($deviceOrders),
        // 'totalOrderPrice' => $this->orders->sum('price'),
        'totalOrderPrice' => $deviceOrders->sum('price'),
        'totalBookedDevicePrice' => $totalBookedDevice, // الإجمالي (live + محفوظ)
        // 'currentDevicePrice' => $this->current_device_cost, // تكلفة الجهاز الحالي
        'totalPrice' => $deviceOrders->sum('price') + $totalBookedDevice,
    ];
}

    private function formatDuration($startTime, $endTime = null)
    {
        $start = Carbon::parse($startTime);
        $now = Carbon::now();

        // احسب إجمالي وقت الـ pauses
        $totalPauseDuration = $this->calculateTotalPauseDuration();

        // لو الـ status = 2 (paused) حالياً
        if ($this->status == 2) {
            // جيب آخر pause اللي مفتوح (resumed_at = null)
            $currentPause = $this->pauses()
                ->whereNull('resumed_at')
                ->orderBy('paused_at', 'desc')
                ->first();

            if ($currentPause) {
                // احسب الوقت لحد ما عمل pause
                $pausedAt = Carbon::parse($currentPause->paused_at);
                $effectiveEnd = $pausedAt;
            } else {
                $effectiveEnd = $now;
            }
        } else {
            // لو مش paused، احسب عادي
            if ($endTime) {
                $end = Carbon::parse($endTime);
                $effectiveEnd = $now->lessThan($end) ? $now : $end;
            } else {
                $effectiveEnd = $now;
            }
        }

        if ($effectiveEnd->lessThan($start)) {
            return "00:00:00";
        }

        // احسب الفرق الكلي واطرح منه وقت الـ pauses
        $totalSeconds = $start->diffInSeconds($effectiveEnd);
        $totalSeconds -= $totalPauseDuration;

        // تأكد إن الوقت مش سالب
        $totalSeconds = max(0, $totalSeconds);

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * احسب إجمالي مدة الـ pauses (بالثواني)
     */
    private function calculateTotalPauseDuration()
    {
        $totalSeconds = 0;

        // جيب كل الـ pauses اللي اتعملت
        $pauses = $this->pauses;

        foreach ($pauses as $pause) {
            if ($pause->resumed_at) {
                // لو الـ pause اتقفل (في resumed_at)
                $pausedAt = Carbon::parse($pause->paused_at);
                $resumedAt = Carbon::parse($pause->resumed_at);
                $totalSeconds += $pausedAt->diffInSeconds($resumedAt);
            } else {
                // لو الـ pause لسه مفتوح (الجهاز paused دلوقتي)
                // مش هنحسبه هنا لأننا وقفنا العد عند paused_at
            }
        }

        return $totalSeconds;
    }

}
