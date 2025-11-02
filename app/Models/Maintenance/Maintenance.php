<?php

namespace App\Models\Maintenance;

use App\Models\User;
use App\Models\Device\Device;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Maintenance extends Model
{
    use UsesTenantConnection , LogsActivity;
     protected $guarded =[];
     public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Maintenance')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Maintenance {$eventName}");
    }
     public function user(){
        return $this->belongsTo(User::class);
     }
     public function device(){
        return $this->belongsTo(Device::class);
     }

}
