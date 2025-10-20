<?php

namespace App\Models\Order;

use App\Models\Order\OrderItem;
use App\Enums\Order\OrderStatus;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Timer\BookedDevice\BookedDevice;

class Order extends Model
{
    use UsesTenantConnection;
    protected $guarded =[];

    public static function boot()
    {
        parent::boot();
        static::creating(function($model){
            $model->number = 'ORD'.'_'.rand(1000,9999).date('m' ).date('y');
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
