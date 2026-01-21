<?php

namespace App\Http\Resources\Timer\BookedDevice;

use App\Enums\SessionDevice\SessionDeviceEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Models\Setting\Param\Param;
use Carbon\CarbonInterface;
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
            $remainingMinutes = Carbon::now()->diffInMinutes(Carbon::parse($this->end_date_time),false);
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
            'status' => $this->status,
            // 'totalHour' => $this->calculateTotalHour($this->start_date_time, $this->end_date_time),
            'currentTime' => $this->formatDuration($this->start_date_time, $this->end_date_time),
            'sessionDevice' => [
                'id' => $this->sessionDevice->id,
                'name' =>$this->sessionDevice->type == SessionDeviceEnum::GROUP->value ? $this->sessionDevice->name :"--",
                'type' => $this->sessionDevice->type,
            ],
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
        // احسب إجمالي وقت الـ pauses
        // $totalPauseDuration = $this->calculateTotalPauseDuration();
        // لو الـ status = 2 (paused) حالياً
        if ($this->status == 2) {
            // جيب آخر pause اللي مفتوح (resumed_at = null)
            $currentPause = $this->pauses()
                ->whereNull('resumed_at')
                ->orderBy('paused_at', 'desc')
                ->first();
            $effectiveEnd = $currentPause
            ? Carbon::parse($currentPause->paused_at)
            : $now;
        } elseif ($this->status == 0) {
          $effectiveEnd = Carbon::parse($endTime);
        } else {//1,3
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
         $totalSeconds -= $this->calculateTotalPauseDuration();
         $totalSeconds = max(0, $totalSeconds);

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
