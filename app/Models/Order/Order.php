<?php

namespace App\Models\Order;

use App\Models\Order\OrderItem;
use App\Enums\Order\OrderStatus;
use App\Trait\UsesTenantConnection;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use App\Models\Timer\BookedDevice\BookedDevice;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;
class Order extends Model
{
    use UsesTenantConnection , LogsActivity, SoftDeletes;
    protected $guarded =[];

    protected bool $ignoreNextUpdate = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Order')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Order {$eventName}");
    }
    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->daily_id;
    }
    public static function boot()
    {
        parent::boot();
        static::creating(function($model){
            if (empty($model->number)) {
                $model->number = 'ORD_' . rand(1000, 9999) . date('m') . date('y');
            }
        });
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function bookedDevice()
    {
        return $this->belongsTo(BookedDevice::class);
    }
}
