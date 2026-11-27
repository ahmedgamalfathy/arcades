<?php

namespace App\Models\Timer\SessionDevice;

use App\Models\Daily\Daily;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class SessionDevice extends Model
{
    use LogsActivity , SoftDeletes, UsesTenantConnection;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        // When deleting SessionDevice, delete all its BookedDevices first
        static::deleting(function ($sessionDevice) {
            // Delete all booked devices (this will trigger their activity logs)
            $sessionDevice->bookedDevices()->each(function ($bookedDevice) {
                $bookedDevice->delete();
            });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('SessionDevice')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "SessionDevice {$eventName}");
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->daily_id;
    }

    public function daily(): BelongsTo
    {
        return $this->belongsTo(Daily::class);
    }

    public function bookedDevices(): HasMany
    {
        return $this->hasMany(BookedDevice::class);
    }

    public function bookedDevicesLatest(): HasMany
    {
        return $this->hasMany(BookedDevice::class)
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('booked_devices')
                    ->groupBy('device_id');
            });
    }
}
