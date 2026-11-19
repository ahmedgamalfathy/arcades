<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;

class RoleAssignmentService
{
    private const PROTECTED_ROLES = [
        'super admin',
        'مدير عام',
    ];

    public function findAssignableRole(User $actor, mixed $roleId): Role
    {
        if (filter_var($roleId, FILTER_VALIDATE_INT) === false) {
            $this->throwInvalidRole();
        }

        $role = Role::on('mysql')
            ->with('permissions')
            ->find((int) $roleId);

        if (! $role || ! $this->canAssignRole($actor, $role)) {
            $this->throwInvalidRole();
        }

        return $role;
    }

    public function isAssignable(User $actor, mixed $roleId): bool
    {
        try {
            $this->findAssignableRole($actor, $roleId);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function assignableRoles(User $actor): Collection
    {
        return Role::on('mysql')
            ->with('permissions')
            ->whereIn('guard_name', Guard::getNames($actor))
            ->get()
            ->filter(fn (Role $role): bool => $this->canAssignRole($actor, $role))
            ->values();
    }

    private function canAssignRole(User $actor, Role $role): bool
    {
        if (! Guard::getNames($actor)->contains($role->guard_name)) {
            return false;
        }

        if (in_array(mb_strtolower($role->name), self::PROTECTED_ROLES, true)) {
            return false;
        }

        $actorPermissions = $actor->getAllPermissions()
            ->where('guard_name', $role->guard_name)
            ->pluck('name')
            ->unique();

        return $role->permissions
            ->pluck('name')
            ->diff($actorPermissions)
            ->isEmpty();
    }

    private function throwInvalidRole(): never
    {
        throw ValidationException::withMessages([
            'roleId' => [__('validation.exists', ['attribute' => 'roleId'])],
        ]);
    }
}
