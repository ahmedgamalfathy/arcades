<?php

namespace App\Models\Timer\SessionDevice;

use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Daily\Daily;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
class SessionDevice extends Model
{
     use UsesTenantConnection , LogsActivity, SoftDeletes;
     protected $guarded =[];

     public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('SessionDevice')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "SessionDevice {$eventName}");
    }
    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->daily_id;
    }
    public function daily()
    {
        return $this->belongsTo(Daily::class);
    }


    public function bookedDevices()
    {
        return $this->hasMany(BookedDevice::class);
    }
    public function bookedDevicesLatest()
    {
        return $this->hasMany(BookedDevice::class)
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                ->from('booked_devices')
                ->groupBy('device_id');
            });
    }

}
