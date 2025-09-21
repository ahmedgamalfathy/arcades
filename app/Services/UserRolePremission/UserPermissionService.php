<?php

namespace App\Services\UserRolePremission;

use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Permission\Models\Permission;

class UserPermissionService
{

    public function getUserPermissions(Authenticatable $user)
    {
        $permissions = Permission::all()->pluck('name')->toArray();

        return array_map(function ($permission) use ($user) {

            return [
                'permissionName' => $permission,
                'access' => $user->can($permission)
            ];
        }, $permissions);
    }
}
