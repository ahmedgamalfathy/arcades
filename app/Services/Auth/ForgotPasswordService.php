<?php

namespace App\Services\Auth;

use App\Mail\ForgotPasswordSendCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

class ForgotPasswordService
{
    public function sendCode(string $email): void
    {
        DB::transaction(function () use ($email): void {
            $code = (string) random_int(100000, 999999);
            $hashedCode = Hash::make($code);
            $user = $this->findUserByEmail($email);

            if (! $user) {
                return;
            }

            $user->update([
                'code' => $hashedCode,
                'expired_at' => now()->addMinutes(5),
            ]);

            defer(function () use ($user, $code): void {
                try {
                    Mail::to($user->email)->send(new ForgotPasswordSendCode($user, $code));
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        });
    }

    public function verifyCode(string $email, string $code): string
    {
        return DB::transaction(function () use ($email, $code): string {
            $dummyHash = Hash::make('000000');
            $user = $this->findUserByEmail($email);

            if (! $this->codeMatches($user?->code, $code, $dummyHash)) {
                return 'invalid';
            }

            if (! $user->expired_at || $user->expired_at->isPast()) {
                return 'expired';
            }

            return 'verified';
        });
    }

    public function changePassword(string $email, string $code, string $password): string
    {
        return DB::transaction(function () use ($email, $code, $password): string {
            $dummyHash = Hash::make('000000');
            $user = $this->findUserByEmail($email, lockForUpdate: true);

            if (! $this->codeMatches($user?->code, $code, $dummyHash)) {
                return 'invalid';
            }

            if (! $user->expired_at || $user->expired_at->isPast()) {
                return 'expired';
            }

            $user->update([
                'password' => Hash::make($password),
                'code' => null,
                'expired_at' => null,
            ]);
            $user->tokens()->delete();

            return 'changed';
        });
    }

    private function codeMatches(?string $hashedCode, string $code, string $dummyHash): bool
    {
        $hasUsableHash = $hashedCode
            && password_get_info($hashedCode)['algoName'] !== 'unknown';
        $candidateHash = $hasUsableHash ? $hashedCode : $dummyHash;

        $matches = Hash::check($code, $candidateHash);

        return $hasUsableHash && $matches;
    }

    private function findUserByEmail(string $email, bool $lockForUpdate = false): ?User
    {
        $normalizedEmail = Str::lower(trim($email));
        $query = User::where('email', $email);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()->first(
            fn (User $user): bool => hash_equals(
                Str::lower(trim($user->email)),
                $normalizedEmail
            )
        );
    }
}
