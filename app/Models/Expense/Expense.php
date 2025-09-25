<?php

namespace App\Models\Expense;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = ['id'];
    protected $casts = [ 'date' => 'datetime'];
    public function user(){
        return $this->belongsTo(User::class);
    }
}
