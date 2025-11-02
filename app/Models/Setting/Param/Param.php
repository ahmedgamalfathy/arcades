<?php

namespace App\Models\Setting\Param;

use Illuminate\Database\Eloquent\Model;
use App\Trait\UsesTenantConnection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Param extends Model 
{
    use UsesTenantConnection , LogsActivity; 
    protected $guarded = [];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Param')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Param {$eventName}");
    }
    
}
