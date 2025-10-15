<?php

namespace App\Models\Timer\BookedDevicePause;

use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Timer\BookedDevice\BookedDevice;

class BookedDevicePause extends Model
{
     use UsesTenantConnection;
     protected $guarded =[];
    protected $casts = ['paused_at' => 'datetime', 'resumed_at' => 'datetime'];

    public function bookedDevice()
    {
        return $this->belongsTo(BookedDevice::class);
    }
}
