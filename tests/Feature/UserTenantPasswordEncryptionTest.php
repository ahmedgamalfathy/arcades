<?php

namespace Tests\Feature;

use App\Casts\EncryptedTenantPassword;
use App\Console\Commands\MakeTenantMigration;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class UserTenantPasswordEncryptionTest extends TestCase
{
    use InteractsWithTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantTesting();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantTesting();
        parent::tearDown();
    }

    public function test_tenant_database_password_is_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $plainPassword = 'tenant-secret-password';
        $user = User::create([
            'name' => 'Tenant Owner',
            'email' => 'tenant-owner@example.com',
            'password' => Hash::make('account-password'),
            'is_active' => 1,
            'database_name' => 'tenant_database',
            'database_username' => 'tenant_user',
            'database_password' => $plainPassword,
        ]);

        $storedPassword = DB::connection('mysql')
            ->table('users')
            ->where('id', $user->id)
            ->value('database_password');

        $this->assertNotSame($plainPassword, $storedPassword);
        $this->assertTrue(EncryptedTenantPassword::isEncrypted($storedPassword));
        $this->assertSame($plainPassword, EncryptedTenantPassword::decryptStored($storedPassword));
        $this->assertSame($plainPassword, $user->fresh()->database_password);
        $this->assertArrayNotHasKey('database_password', $user->toArray());
    }

    public function test_migration_encrypts_existing_plaintext_tenant_passwords(): void
    {
        $plainPassword = 'legacy-tenant-password';
        $userId = DB::connection('mysql')->table('users')->insertGetId([
            'name' => 'Legacy Tenant',
            'email' => 'legacy-tenant@example.com',
            'password' => Hash::make('account-password'),
            'is_active' => 1,
            'database_password' => $plainPassword,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The transitional cast keeps rolling deployments functional until migration runs.
        $this->assertSame($plainPassword, User::findOrFail($userId)->database_password);

        $migration = require database_path('migrations/Main/2026_11_22_000000_encrypt_tenant_database_passwords.php');
        $migration->up();

        $storedPassword = DB::connection('mysql')
            ->table('users')
            ->where('id', $userId)
            ->value('database_password');

        $this->assertNotSame($plainPassword, $storedPassword);
        $this->assertTrue(EncryptedTenantPassword::isEncrypted($storedPassword));
        $this->assertSame($plainPassword, EncryptedTenantPassword::decryptStored($storedPassword));
        $this->assertSame($plainPassword, User::findOrFail($userId)->database_password);
    }

    public function test_tenant_migration_command_receives_the_decrypted_password(): void
    {
        User::create([
            'name' => 'Command Tenant',
            'email' => 'command-tenant@example.com',
            'password' => Hash::make('account-password'),
            'is_active' => 1,
            'database_name' => 'command_tenant_database',
            'database_username' => 'command_tenant_user',
            'database_password' => 'command-tenant-secret',
        ]);

        $command = new class extends MakeTenantMigration
        {
            public function option($key = null)
            {
                return $key === 'tenant' ? [] : null;
            }

            public function tenants()
            {
                return $this->getTenants();
            }
        };

        $tenant = $command->tenants()->sole();

        $this->assertInstanceOf(User::class, $tenant);
        $this->assertSame('command-tenant-secret', $tenant->database_password);
    }

    public function test_migration_preserves_unprefixed_laravel_ciphertext(): void
    {
        $plainPassword = 'previously-encrypted-password';
        $oldCiphertext = Crypt::encryptString($plainPassword);
        $userId = DB::connection('mysql')->table('users')->insertGetId([
            'name' => 'Previously Encrypted Tenant',
            'email' => 'previously-encrypted@example.com',
            'password' => Hash::make('account-password'),
            'is_active' => 1,
            'database_password' => $oldCiphertext,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/Main/2026_11_22_000000_encrypt_tenant_database_passwords.php');
        $migration->up();

        $storedPassword = DB::connection('mysql')
            ->table('users')
            ->where('id', $userId)
            ->value('database_password');

        $this->assertSame(EncryptedTenantPassword::PREFIX.$oldCiphertext, $storedPassword);
        $this->assertSame($plainPassword, User::findOrFail($userId)->database_password);
    }

    public function test_migration_rejects_corrupted_encrypted_tenant_passwords(): void
    {
        DB::connection('mysql')->table('users')->insert([
            'name' => 'Corrupted Tenant',
            'email' => 'corrupted-tenant@example.com',
            'password' => Hash::make('account-password'),
            'is_active' => 1,
            'database_password' => EncryptedTenantPassword::PREFIX.'invalid-ciphertext',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/Main/2026_11_22_000000_encrypt_tenant_database_passwords.php');

        $this->expectException(DecryptException::class);
        $migration->up();
    }
}
