<?php

namespace App\Models\Device\DeviceTime;

use App\Models\Device\Device;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Device\DeviceType\DeviceType;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DeviceTime extends Model
{

    use UsesTenantConnection, LogsActivity, SoftDeletes;
    protected $guarded = [];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('DeviceTime')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "DeviceTime {$eventName}");
    }
    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class);
    }
    public function devices()
    {
        return $this->belongsToMany(Device::class, 'device_device_time');
    }

}
