<?php

namespace App\Models\Maintenance;

use App\Models\User;
use App\Models\Device\Device;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    use UsesTenantConnection;
     protected $guarded =[];
     public function user(){
        return $this->belongsTo(User::class);
     }
     public function device(){
        return $this->belongsTo(Device::class);
     }

}
