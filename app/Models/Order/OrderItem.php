<?php

namespace App\Models\Order;

use App\Models\Product\Product;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
class OrderItem extends Model
{
    use UsesTenantConnection , LogsActivity;
    protected $guarded = [];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('OrderItem')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "OrderItem {$eventName}");
    }
    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->order->daily_id;
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
