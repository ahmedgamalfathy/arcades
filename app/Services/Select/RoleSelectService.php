<?php

namespace App\Services\Select;

use App\Services\User\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;

class RoleSelectService
{
    public function getAllRoles()
    {
        return app(RoleAssignmentService::class)
            ->assignableRoles(Auth::user())
            ->map(fn ($role) => [
                'value' => $role->id,
                'label' => $role->name,
            ])
            ->values();
    }
}
