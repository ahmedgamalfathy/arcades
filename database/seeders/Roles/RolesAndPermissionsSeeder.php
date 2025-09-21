<?php

namespace Database\Seeders\Roles;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // premissions
        $permissions = [
            'all_users',
            'create_user',
            'edit_user',
            'update_user',
            'destroy_user',
            'change_user_status',

            'all_roles',
            'create_role',
            'edit_role',
            'update_role',
            'destroy_role',


            'all_params',
            'create_param',
            'edit_param',
            'update_param',
            'destroy_param',

        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission], [
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        // roles

        $admin = Role::create(['name' => 'admin']);
        $adminPermissions = [
            'all_users',
            'edit_user',
            'change_user_status',

        ];
        $admin->givePermissionTo($adminPermissions);
        $superAdmin = Role::create(['name' => 'super admin']);
        $superAdmin->givePermissionTo(Permission::get());

    }
}
