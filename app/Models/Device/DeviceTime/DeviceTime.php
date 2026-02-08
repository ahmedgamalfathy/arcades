<?php

namespace App\Models\Device\DeviceTime;

use App\Models\Device\Device;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Device\DeviceType\DeviceType;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Enums\BookedDevice\BookedDeviceEnum;

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
    
    /**
     * العلاقة مع BookedDevice
     */
    public function bookedDevices()
    {
        return $this->hasMany(\App\Models\Timer\BookedDevice\BookedDevice::class, 'device_time_id');
    }
    
    /**
     * التحقق من وجود حجوزات نشطة
     */
    public function hasActiveBookings(): bool
    {
        return $this->bookedDevices()
            ->whereNot('status', BookedDeviceEnum::FINISHED->value) // نشط
            ->exists();
    }
    
    /**
     * التحقق من إمكانية حذف وقت التشغيل
     */
    public function canBeDeleted(): bool
    {
        // إذا كان مستخدم في حجوزات نشطة، مينفعش يتحذف
        if ($this->hasActiveBookings()) {
            return false;
        }
        
        // إذا مش مستخدم في حجوزات نشطة، يتحذف Soft Delete
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
            return "لا يمكن حذف وقت التشغيل لأنه مستخدم حالياً في {$activeBookingsCount} حجز نشط. يجب إنهاء الحجوزات أولاً.";
        }
        
        return '';
    }

}
