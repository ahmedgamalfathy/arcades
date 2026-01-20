<?php

namespace App\Models\Timer\BookedDevice;

use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Timer\BookedDevicePause\BookedDevicePause;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\SessionDevice\SessionDevice;
use App\Models\Device\Device;
use App\Models\Order\Order;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;
class BookedDevice extends Model
{
     use UsesTenantConnection , LogsActivity;

      protected $guarded =[];
     public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('BookedDevice')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "BookedDevice {$eventName}");
    }
    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->sessionDevice->daily_id;
    }
      protected $casts = [
        'start_date_time' => 'datetime',
        'end_date_time'   => 'datetime',
    ];

    public function pauses()
    {
        return $this->hasMany(BookedDevicePause::class);
    }
//sessionDevice,deviceType,deviceTime,device
   public function sessionDevice()
    {
        return $this->belongsTo(SessionDevice::class);
    }
    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class);
    }
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
    public function deviceTime()
    {
        return $this->belongsTo(DeviceTime::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    public function calculateUsedSeconds(): int
    {
        $start=$this->start_date_time;
        $end = $this->end_date_time ?? Carbon::now();
        $total = $start->diffInSeconds($end);
        return max(0, $total - $this->total_paused_seconds);
    }

    public function calculatePrice(): float
    {
        $hours = $this->calculateUsedSeconds() / 3600;
        return round($hours * ($this->deviceTime->rate ?? 0), 2);
    }
// في BookedDevice Model
    public function getCurrentDeviceCostAttribute(): float
    {
        // لو الجهاز منتهي
        if ($this->status == 0) {
            return $this->period_cost ?? 0;
        }

        // لو الجهاز لسه شغال
        $start = Carbon::parse($this->start_date_time);
        $now = Carbon::now();

        // حدد نقطة النهاية
        if ($this->end_date_time) {
            $end = Carbon::parse($this->end_date_time);
            $effectiveEnd = $now->lessThan($end) ? $now : $end;
        } else {
            $effectiveEnd = $now;
        }

        // احسب الوقت الفعلي (مع خصم الـ pauses)
        $totalSeconds = $start->diffInSeconds($effectiveEnd);
        $totalSeconds -= $this->calculateTotalPauseDuration();
        $totalSeconds = max(0, $totalSeconds);

        // حول لساعات واضرب في السعر
        $hours = $totalSeconds / 3600;
        return round($hours * ($this->deviceTime->rate ?? 0), 2);
    }

    // دالة مساعدة لحساب وقت الـ pauses
    private function calculateTotalPauseDuration(): int
    {
        $totalSeconds = 0;

        if (!method_exists($this, 'pauses')) {
            return 0;
        }

        $pauses = $this->pauses;

        if (!$pauses || $pauses->isEmpty()) {
            return 0;
        }

        foreach ($pauses as $pause) {
            if ($pause->resumed_at) {
                $pausedAt = Carbon::parse($pause->paused_at);
                $resumedAt = Carbon::parse($pause->resumed_at);
                $totalSeconds += $pausedAt->diffInSeconds($resumedAt);
            }
        }

        return $totalSeconds;
    }
    public function getTotalCostAttribute()
    {
        return ($this->period_cost ?? 0) + ($this->orders->sum('price') ?? 0);
    }


}
