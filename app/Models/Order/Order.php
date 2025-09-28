<?php

namespace App\Models\Order;

use App\Models\Order\OrderItem;
use App\Enums\Order\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
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
}
