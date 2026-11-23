<?php

use App\Casts\EncryptedTenantPassword;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('users', function (Blueprint $table): void {
            $table->text('database_password')->nullable()->change();
        });

        DB::connection('mysql')
            ->table('users')
            ->select(['id', 'database_password'])
            ->whereNotNull('database_password')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    if (EncryptedTenantPassword::isEncrypted($user->database_password)) {
                        // Validate existing ciphertext so corrupted credentials fail the migration.
                        EncryptedTenantPassword::decryptStored($user->database_password);

                        continue;
                    }

                    try {
                        // Preserve values written by Laravel's native encrypted cast before this migration.
                        Crypt::decryptString($user->database_password);

                        $encryptedPassword = EncryptedTenantPassword::PREFIX.$user->database_password;
                    } catch (DecryptException) {
                        $encryptedPassword = EncryptedTenantPassword::encryptForStorage($user->database_password);
                    }

                    DB::connection('mysql')
                        ->table('users')
                        ->where('id', $user->id)
                        ->update([
                            'database_password' => $encryptedPassword,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: rolling back must never restore plaintext credentials.
    }
};
