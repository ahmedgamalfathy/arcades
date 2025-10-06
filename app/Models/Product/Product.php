<?php

namespace App\Models\Product;

use App\Models\Media\Media;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use UsesTenantConnection;
    protected $guarded =[];
    public function media(){
        return $this->belongsTo(Media::class);
    }
}
