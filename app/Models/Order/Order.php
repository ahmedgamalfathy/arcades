<?php

namespace App\Models\Order;

use App\Models\Timer\BookedDevice\BookedDevice;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use LogsActivity , SoftDeletes, UsesTenantConnection;

    protected $guarded = [];

    protected bool $ignoreNextUpdate = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->dontSubmitEmptyLogs()
            ->logOnly([]) // لا تسجل أي حقول تلقائياً
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        $activity->daily_id = $this->daily_id;
    }

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->number)) {
                $model->number = 'ORD_'.rand(1000, 9999).date('m').date('y');
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bookedDevice(): BelongsTo
    {
        return $this->belongsTo(BookedDevice::class);
    }
}
