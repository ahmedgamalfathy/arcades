<?php

namespace App\Models\Device\DeviceType;

use App\Models\Device\Device;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceTime\DeviceTime;

class DeviceType extends Model
{
    protected $guarded = [];

    public function deviceTimes()
    {
        return $this->hasMany(DeviceTime::class);
    }
    public function devices()
    {
        return $this->hasMany(Device::class,'device_type_id');
    }


}
