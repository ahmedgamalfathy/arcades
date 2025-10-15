<?php

namespace App\Models\Timer\BookedDevice;

use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Timer\BookedDevicePause\BookedDevicePause;

class BookedDevice extends Model
{
     use UsesTenantConnection;
      protected $guarded =[];
      protected $casts = [
        'start_date_time' => 'datetime',
        'end_date_time'   => 'datetime',
    ];

    public function pauses()
    {
        return $this->hasMany(BookedDevicePause::class);
    }

    public function deviceTime()
    {
        return $this->belongsTo(DeviceTime::class);
    }

    public function calculateUsedSeconds(): int
    {
        $start=$this->start_date_time;
        $end = $this->end_date_time ?? now();
        $total = $start->diffInSeconds($end);
        return max(0, $total - $this->total_paused_seconds);
    }

    public function calculatePrice(): float
    {
        $hours = $this->calculateUsedSeconds() / 3600;
        return round($hours * ($this->deviceTime->rate ?? 0), 2);
    }
}
