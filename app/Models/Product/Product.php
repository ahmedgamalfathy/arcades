<?php

namespace App\Models\Product;

use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded =[];
    public function media(){
        return $this->belongsTo(Media::class);
    }
}
