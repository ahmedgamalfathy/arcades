<?php

namespace App\Models\Device\DeviceType;

use App\Models\Device\Device;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Device\DeviceTime\DeviceTime;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Enums\BookedDevice\BookedDeviceEnum;

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
    
    /**
     * التحقق من وجود أجهزة شغالة في تيم
     */
    public function hasDevicesWithActiveBookings(): bool
    {
        return $this->devices()
            ->whereHas('bookedDevices', function($query) {
                $query->whereNot('status',BookedDeviceEnum::FINISHED->value); // نشط
            })
            ->exists();
    }
    
    /**
     * التحقق من إمكانية حذف نوع الجهاز
     */
    public function canBeDeleted(): bool
    {
        // إذا فيه أجهزة شغالة في تيم، مينفعش يتحذف
        if ($this->hasDevicesWithActiveBookings()) {
            return false;
        }
        
        // إذا مفيش أجهزة شغالة، يتحذف Soft Delete هو والأجهزة
        return true;
    }
    
    /**
     * الحصول على سبب عدم إمكانية الحذف
     */
    public function getDeletionBlockReason(): string
    {
        if ($this->hasDevicesWithActiveBookings()) {
            $activeDevicesCount = $this->devices()
                ->whereHas('bookedDevices', function($query) {
                    $query->whereNot('status', BookedDeviceEnum::FINISHED->value);
                })
                ->count();
            return "لا يمكن حذف نوع الجهاز لأن {$activeDevicesCount} من أجهزته شغالة حالياً في التيم. يجب إنهاء الحجوزات أولاً.";
        }
        
        return '';
    }


}
