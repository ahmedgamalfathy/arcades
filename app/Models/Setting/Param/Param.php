<?php

namespace App\Models\Setting\Param;

use Illuminate\Database\Eloquent\Model;
use App\Trait\UsesTenantConnection;

class Param extends Model 
{
    use UsesTenantConnection; 
    protected $guarded = [];


}
