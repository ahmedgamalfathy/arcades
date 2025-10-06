<?php

namespace App\Models\Order;

use App\Models\Product\Product;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use UsesTenantConnection;
    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
