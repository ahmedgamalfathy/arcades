<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\Roles\RolesAndPermissionsSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call([
        //     // RolesAndPermissionsSeeder::class,
        //     // UserSeeder::class,
        //     // MediaSeeder::class
        // ]);
        if (config('database.default') === 'mysql') {
            $this->call([
                UserSeeder::class,
                RolesAndPermissionsSeeder::class,
                // MediaSeeder::class
            ]);
        }
        if (config('database.default') === 'tenant') {
            // قاعدة بيانات التيننت
            $this->call([
                MediaSeeder::class
            ]);
        }
    }
}
