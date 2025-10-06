<?php

namespace App\Models\Expense;

use App\Models\User;
use App\Trait\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes ,UsesTenantConnection;
    protected $guarded = ['id'];
    protected $casts = [ 'date' => 'datetime'];
    public function user(){
        return $this->belongsTo(User::class);
    }
}
