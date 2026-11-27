<?php

namespace App\Models\Device;

use App\Enums\BookedDevice\BookedDeviceEnum;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Maintenance\Maintenance;
use App\Models\Media\Media;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Device extends Model
{
    use LogsActivity , SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'name',
        'status',
        'device_type_id',
        'media_id',
    ];

    public static function boot()
    {
        parent::boot();

        // منع الحذف إذا كان الجهاز شغال في تيم
        static::deleting(function ($device) {
            if (! $device->canBeDeleted()) {
                throw new \Exception($device->getDeletionBlockReason());
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Device')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName) => "Device {$eventName}");
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class, 'device_type_id')->withTrashed();
    }

    public function deviceTimes(): BelongsToMany
    {
        return $this->belongsToMany(DeviceTime::class, 'device_device_time');
    }

    public function deviceTimeSpecial(): HasMany
    {
        return $this->hasMany(DeviceTime::class, 'device_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'device_id');
    }

    public function bookedDevices(): HasMany
    {
        return $this->hasMany(BookedDevice::class, 'device_id');
    }

    /**
     * التحقق من وجود حجوزات نشطة (شغال في تيم)
     */
    public function hasActiveBookings(): bool
    {
        return $this->bookedDevices()
            ->whereNot('status', BookedDeviceEnum::FINISHED->value) // نشط
            ->exists();
    }

    /**
     * التحقق من إمكانية حذف الجهاز (منع الحذف فقط إذا شغال في تيم)
     */
    public function canBeDeleted(): bool
    {
        // إذا كان شغال في تيم، مينفعش يتحذف خالص
        if ($this->hasActiveBookings()) {
            return false;
        }

        // إذا مش شغال، يتحذف Soft Delete (مش بيأثر على التقارير)
        return true;
    }

    /**
     * الحصول على سبب عدم إمكانية الحذف
     */
    public function getDeletionBlockReason(): string
    {
        if ($this->hasActiveBookings()) {
            $activeBookingsCount = $this->bookedDevices()
                ->whereNot('status', BookedDeviceEnum::FINISHED->value)
                ->count();

            return "لا يمكن حذف الجهاز لأنه شغال حالياً في التيم ({$activeBookingsCount} حجز نشط). يجب إنهاء الحجوزات أولاً.";
        }

        return '';
    }
}
