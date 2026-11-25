<?php

namespace App\Services\Select;

use App\Models\User;

class UserSelectService
{
    public function getAllUsers()
    {
        $actor = auth()->user();

        return User::query()
            ->managedBy($actor)
            ->whereNot('id', $actor->id)
            ->get(['id as value', 'name as label']);
    }
}
