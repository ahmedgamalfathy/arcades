<?php

namespace App\Models\Device\DeviceType;

use App\Models\Device\Device;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Device\DeviceTime\DeviceTime;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DeviceType extends Model
{
    use UsesTenantConnection , LogsActivity, SoftDeletes;
    protected $guarded = [];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('DeviceType')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "DeviceType {$eventName}");
    }
    public function deviceTimes()
    {
        return $this->hasMany(DeviceTime::class);
    }
    public function devices()
    {
        return $this->hasMany(Device::class,'device_type_id');
    }


}
