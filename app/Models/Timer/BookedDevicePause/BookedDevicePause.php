<?php

namespace App\Models\Timer\BookedDevicePause;

use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
class BookedDevicePause extends Model
{
     use UsesTenantConnection , LogsActivity;
     protected $guarded =[];
     public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('BookedDevicePause')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName) {
            return $eventName === 'updated'
            ? 'BookedDevicePause resumed'
            : "BookedDevicePause {$eventName}";
            });

    }
    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->bookedDevice->sessionDevice->daily_id;
    }
    protected $casts = ['paused_at' => 'datetime', 'resumed_at' => 'datetime'];

    public function bookedDevice()
    {
        return $this->belongsTo(BookedDevice::class);
    }
}
