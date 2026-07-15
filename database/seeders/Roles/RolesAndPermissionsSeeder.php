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

        // permissions
        $permissions = [
            'all_users',
            'create_user',
            'edit_user',
            'update_user',
            'destroy_user',
            'restore_user',
            'force_delete_user',
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
            'all_expenses',
            'create_expenses',
            'restore_expense',
            'force_delete_expense',
            'edit_expense',
            'update_expense',
            'destroy_expense',
//maintenance permissions
            'maintenances',
            'create_maintenance',
            'edit_maintenance',
            'update_maintenance',
            'destroy_maintenance',
//orders ,create_orders , edit_order ,update_order ,destroy_order
            'orders',
            'create_orders',
            'edit_order',
            'update_order',
            'destroy_order',
            'restore_order',
            'force_delete_order',
            'change_order_status',
            'change_order_payment_status',
//media permissions
            'media',
            'create_media',
            'edit_media',
            'update_media',
            'destroy_media',
//devices ,create_devices, edit_device,update_device ,destroy_device
            'devices',
            'create_devices',
            'edit_device',
            'update_device',
            'destroy_device',
            'change_device_status',
//device_times, create_device_times, edit_device_time, update_device_time, destroy_device_time
            'device_times',
            'create_device_times',
            'edit_device_time',
            'update_device_time',
            'destroy_device_time',
//device_types, create_device_types, edit_device_type, update_device_type, destroy_device_type
            'device_types',
            'create_device_types',
            'edit_device_type',
            'update_device_type',
            'destroy_device_type',
//daily ,create_daily , edit_daily ,update_daily ,destroy_daily ,close_daily ,daily_report
            'daily',
            'create_daily',
            'edit_daily',
            'update_daily',
            'destroy_daily',
            'close_daily',
            'daily_report',
            'view_daily_activity',
//all_params , create_param , edit_param ,update_param ,destroy_param
            'all_params',
            'create_param',
            'edit_param',
            'update_param',
            'destroy_param',
//notifications , auth_unread_notifications , auth_read_notifications , auth_read_notification , auth_delete_notifications , auth_delete_notification
            'notifications',
            'auth_unread_notifications',
            'auth_read_notifications',
            'auth_read_notification',
            'auth_delete_notifications',
            'auth_delete_notification',
            'view_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission], [
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        // roles

        $admin = Role::create(['name' => 'admin']);
        // $adminPermissions = [
        //     'all_users',
        //     'edit_user',

        //     'products',
        //     'edit_product',



        //     'devices',
        //     'edit_device',

        //     'device_times',
        //     'edit_device_time',

        //     'device_types',
        //     'edit_device_type',

        //     'internalOrders',
        //     'externalOrders',
        //     'edit_order',
        // ];
        // $admin->givePermissionTo($adminPermissions);
        $superAdmin = Role::create(['name' => 'super admin']);
        $superAdmin->givePermissionTo(Permission::get());
        $admin->givePermissionTo(Permission::get());
    }
}
