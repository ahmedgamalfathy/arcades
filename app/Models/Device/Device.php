<?php

namespace App\Models\Device;

use App\Models\Media\Media;
use App\Models\Maintenance\Maintenance;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;

class Device extends Model
{
    protected $guarded = [];
    public function media()
    {
        return $this->belongsTo(Media::class);
    }
    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class,'device_type_id');
    }
    public function deviceTimes()
    {
        return $this->belongsToMany(DeviceTime::class, 'device_device_time');
    }
    public function maintenances()
    {
        return $this->hasMany(Maintenance::class,'device_id');
    }

}
