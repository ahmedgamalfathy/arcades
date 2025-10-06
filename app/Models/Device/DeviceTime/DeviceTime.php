<?php

namespace App\Models\Device\DeviceTime;

use App\Models\Device\Device;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceType\DeviceType;

class DeviceTime extends Model
{
    use UsesTenantConnection;
    protected $guarded = [];

    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class);
    }
    public function devices()
    {
        return $this->belongsToMany(Device::class, 'device_device_time');
    }

}
