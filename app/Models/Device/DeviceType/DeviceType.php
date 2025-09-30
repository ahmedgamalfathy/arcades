<?php

namespace App\Models\Device\DeviceType;

use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceTime\DeviceTime;

class DeviceType extends Model
{
    protected $guarded = [];

    public function deviceTimes()
    {
        return $this->hasMany(DeviceTime::class);
    }
    
}
