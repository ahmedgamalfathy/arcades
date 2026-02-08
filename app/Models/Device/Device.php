<?php

namespace App\Models\Device;

use App\Models\Media\Media;
use App\Models\Maintenance\Maintenance;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device\DeviceTime\DeviceTime;
use App\Models\Device\DeviceType\DeviceType;
use App\Models\Timer\BookedDevice\BookedDevice;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Enums\BookedDevice\BookedDeviceEnum;
class Device extends Model
{
    use UsesTenantConnection , LogsActivity, SoftDeletes;
    protected $guarded = [];

    public static function boot()
    {
        parent::boot();

        // منع الحذف إذا كان الجهاز شغال في تيم
        static::deleting(function ($device) {
            if (!$device->canBeDeleted()) {
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
            ->setDescriptionForEvent(fn(string $eventName) => "Device {$eventName}");
    }
    public function media()
    {
        return $this->belongsTo(Media::class);
    }
    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class,'device_type_id')->withTrashed();
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

    /**
     * التحقق من وجود حجوزات نشطة (شغال في تيم)
     */
    public function hasActiveBookings(): bool
    {
        return $this->bookedDevices()
            ->whereNot('status',BookedDeviceEnum::FINISHED->value) // نشط
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
