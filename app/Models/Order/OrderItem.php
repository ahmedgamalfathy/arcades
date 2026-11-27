<?php

namespace App\Models\Order;

use App\Models\Product\Product;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use UsesTenantConnection;

    protected $guarded = [];

    // لا نستخدم LogsActivity trait - التسجيل يتم من Order فقط

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
