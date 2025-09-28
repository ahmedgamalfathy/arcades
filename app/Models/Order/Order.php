<?php

namespace App\Models\Order;

use App\Enums\Order\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded =[];
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }
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
