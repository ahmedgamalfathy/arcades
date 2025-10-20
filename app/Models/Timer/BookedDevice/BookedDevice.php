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
