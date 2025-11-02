<?php

namespace App\Models\Product;

use App\Models\Media\Media;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Product extends Model
{
    use UsesTenantConnection , LogsActivity;
    protected $guarded =[];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Product')
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn(string $eventName) => "Product {$eventName}");
    }
    public function media(){
        return $this->belongsTo(Media::class);
    }
}
