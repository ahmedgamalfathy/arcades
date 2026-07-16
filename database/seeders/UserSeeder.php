<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = Role::where('name', 'super admin')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $superAdminUser = User::updateOrCreate(
            ['email' => env('SEED_SUPER_ADMIN_EMAIL', 'super-admin@example.com')],
            [
                'name' => env('SEED_SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($this->requiredEnv('SEED_SUPER_ADMIN_PASSWORD')),
                'database_name' => env('SEED_SUPER_ADMIN_DB_NAME'),
                'database_username' => env('SEED_SUPER_ADMIN_DB_USERNAME'),
                'database_password' => env('SEED_SUPER_ADMIN_DB_PASSWORD'),
                'app_key' => env('SEED_SUPER_ADMIN_APP_KEY', '_super_admin'),
            ]
        );
        $superAdminUser->assignRole($superAdminRole);

        $adminUser = User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('SEED_ADMIN_NAME', 'Admin'),
                'password' => Hash::make($this->requiredEnv('SEED_ADMIN_PASSWORD')),
                'database_name' => env('SEED_ADMIN_DB_NAME'),
                'database_username' => env('SEED_ADMIN_DB_USERNAME'),
                'database_password' => env('SEED_ADMIN_DB_PASSWORD'),
                'app_key' => env('SEED_ADMIN_APP_KEY', '_admin'),
            ]
        );
        $adminUser->assignRole($adminRole);
    }

    private function requiredEnv(string $key): string
    {
        $value = env($key);

        if (blank($value)) {
            throw new \RuntimeException("Missing required seed environment value: {$key}");
        }

        return $value;
    }
}
