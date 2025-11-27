<?php

namespace App\Services\Select;

use Spatie\Permission\Models\Role;

class RoleSelectService
{
    public function getAllRoles()
    {
       return Role::on('mysql')->whereNot('name', 'مدير عام')->get(['id as value', 'name as label']);
    }
}



