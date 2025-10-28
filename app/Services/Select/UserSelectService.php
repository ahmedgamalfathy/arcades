<?php

namespace App\Services\Select;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserSelectService
{
    public function getAllUsers()
    {
        $user = User::find(auth('api')->user()->id);
        if($user->user_id == null && $user->database_name != null && $user->database_password != null ){
            $users = User::where('user_id', $user->id)->get(['id as value','name as label']);
        }else{
            $users = User::whereNot('id', $user->id)->where('user_id',$user->id)->get(['id as value','name as label']);
        }
        return $users;
    }
}
