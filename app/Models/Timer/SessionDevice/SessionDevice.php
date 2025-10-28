<?php

namespace App\Models\Timer\SessionDevice;

use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\Timer\BookedDevice\BookedDevice;
use App\Models\Daily\Daily;

class SessionDevice extends Model
{
     use UsesTenantConnection;
     protected $guarded =[];
    public function daily()
    {
        return $this->belongsTo(Daily::class);
    }


    public function bookedDevices()
    {
        return $this->hasMany(BookedDevice::class);
    }

}
