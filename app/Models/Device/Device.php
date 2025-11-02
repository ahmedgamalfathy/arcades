<?php

namespace App\Models\Device;

use App\Models\Media\Media;
use App\Models\Maintenance\Maintenance;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class Device extends Model
{
    use UsesTenantConnection , LogsActivity;
    protected $guarded = [];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Device')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Device {$eventName}");
    }
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
    public  function deviceTimeSpecial(){
        return $this->hasMany(DeviceTime::class,'device_id');
    }
    public function maintenances()
    {
        return $this->hasMany(Maintenance::class,'device_id');
    }
     public function bookedDevices()
    {
        return $this->hasMany(BookedDevice::class,'device_id');
    }
}
