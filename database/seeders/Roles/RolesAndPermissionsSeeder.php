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

            'products',
            'create_products',
            'edit_product',
            'update_product',
            'destroy_product',
//expenses ,create_expenses ,edit_expense ,update_expense,destroy_expense
            'internalExpenses',
            'externalExpenses',
            'forceDelete',
            'destroy_restore',
            'destroy_forceDelete',
            'edit_expense',
            'update_expense',
            'destroy_expense',
//maintenaces ,create_maintenaces ,edit_maintenace ,update_maintenace ,destroy_maintenace
            'maintenaces',
            'create_maintenaces',
            'edit_maintenace',
            'update_maintenace',
            'destroy_maintenace',
//orders ,create_orders , edit_order ,update_order ,destroy_order
            'internalOrders',
            'externalOrders',
            'create_orders',
            'edit_order',
            'update_order',
            'destroy_order',
 // medias ,create_medias,edit_meida ,update_meida ,destroy_meida
             'medias',
            'create_medias',
            'edit_meida',
            'update_meida',
            'destroy_meida',
//devices ,create_devices, edit_device,update_device ,destroy_device
            'devices',
            'create_devices',
            'edit_device',
            'update_device',
            'destroy_device',
            'devices_changeStatus',
//deviceTimes ,create_deviceTimes,edit_deviceTime,update_deviceTime,destroy_deviceTime
            'deviceTimes',
            'create_deviceTimes',
            'edit_deviceTime',
            'update_deviceTime',
            'destroy_deviceTime',
//deviceTypes , create_deviceTypes ,edit_deviceType ,update_deviceType ,destroy_deviceType
            'deviceTypes',
            'create_deviceTypes',
            'edit_deviceType',
            'update_deviceType',
            'destroy_deviceType',

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

            'products',
            'edit_product',

            'internalExpenses',
            'externalExpenses',

            'devices',
            'edit_device',

            'deviceTimes',
            'edit_deviceTime',

            'deviceTypes',
            'edit_deviceType',

            'internalOrders',
            'externalOrders',
            'edit_order',
        ];
        $admin->givePermissionTo($adminPermissions);
        $superAdmin = Role::create(['name' => 'super admin']);
        $superAdmin->givePermissionTo(Permission::get());

    }
}
