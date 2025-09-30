<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminUser = User::create([
            'name' => 'elmo',
            'email' => 'elmo@gmail.com',
            'password' => Hash::make('elmo123456'),
            'database_name'=> 'u824100506_user1',
            'app_key'=>'_h123456'
        ]);
        $superAdminRole = Role::where('name', 'super admin')->first();
        $superAdminUser->assignRole($superAdminRole);

        $adminUser = User::create([
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('elmo123456'),
            'app_key'=>'h123456'
        ]);
        $adminRole = Role::where('name', 'admin')->first();
        $adminUser->assignRole($adminRole);


   }

}
