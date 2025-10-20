<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

class MakeTenantMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate
                            {--tenant=* : Specific tenant IDs to migrate}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after migrating}
                            {--path= : The path to the tenant migrations directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations on all tenant databases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting tenant migrations...');

        // Get tenant configurations from main database
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found in the database.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s).");

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        foreach ($tenants as $tenant) {
            try {
                $this->migrateTenant($tenant);
                $bar->advance();
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to migrate tenant {$tenant->id}: " . $e->getMessage());
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Tenant migrations completed!');

        return Command::SUCCESS;
    }

    /**
     * Get tenants from the main database
     */
    protected function getTenants()
    {
        $tenantIds = $this->option('tenant');

        $query = DB::connection('mysql') // Your main database connection
            ->table('users')
            ->select('id', 'name','database_name', 'database_username', 'database_password')
            ->whereNotNull('database_username'); // Only users with tenant databases

        if (!empty($tenantIds)) {
            $query->whereIn('id', $tenantIds);
        }

        return $query->get();
    }

    /**
     * Run migrations for a specific tenant
     */
    protected function migrateTenant($tenant)
    {
        $this->newLine();
        $this->line("Migrating tenant: {$tenant->name} (ID: {$tenant->id})");

        // Configure tenant database connection
        $connectionName = "tenant_{$tenant->id}";

        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $tenant->db_host ?? env('DB_HOST', '127.0.0.1'),
            'port' => $tenant->db_port ?? env('DB_PORT', '3306'),
            'database' => $tenant->database_name,
            'username' => $tenant->database_username,
            'password' => $tenant->database_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Test connection
        try {
            DB::connection($connectionName)->getPdo();
        } catch (\Exception $e) {
            throw new \Exception("Cannot connect to database: " . $e->getMessage());
        }

        // Run migrations
            $params = [
            '--database' => $connectionName,
            '--force' => true,
            ];

            if ($path = $this->option('path')) {
                $params['--path'] = $path;
            }

            if ($this->option('fresh')) {
                $this->warn("Running fresh migration for tenant {$tenant->id}...");
                Artisan::call('migrate:fresh', $params);
            } else {
                Artisan::call('migrate', $params);
            }

        $this->info(Artisan::output());

        // Run seeders if requested
        if ($this->option('seed')) {
            $this->line("Seeding tenant {$tenant->id}...");
            $seeders = [
                \Database\Seeders\MediaSeeder::class,
                \Database\Seeders\ParamSeeder::class,
            ];
            foreach ($seeders as $seederClass) {
                $this->line(" → Running {$seederClass}");
                Artisan::call('db:seed', [
                    '--database' => $connectionName,
                    '--force' => true,
                    '--class' => $seederClass,
                ]);
                $this->info(Artisan::output());
            }
        }
        // if ($this->option('seed')) {
        //     $this->line("Seeding tenant {$tenant->id}...");
        //     Artisan::call('db:seed', [
        //         '--database' => $connectionName,
        //         '--force' => true,
        //         '--class' => 'Database\\Seeders\\MediaSeeder', // ← هنا تحط اسم الـ seeder اللي عايز تشغله
        //     ]);
        // }


        // Purge connection
        DB::purge($connectionName);

        $this->info("✓ Tenant {$tenant->id} migrated successfully");
    }
}
