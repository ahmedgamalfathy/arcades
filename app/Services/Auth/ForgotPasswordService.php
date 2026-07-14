<?php

namespace App\Services\Auth;

use App\Mail\ForgotPasswordSendCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordService
{
    public function sendCode(string $email): bool
    {
        return DB::transaction(function () use ($email): bool {
            $user = User::where('email', $email)->first();

            if (!$user) {
                return false;
            }

            $user->update([
                'code' => rand(100000, 999999),
                'expired_at' => now()->addMinutes(5),
            ]);

            Mail::to($email)->send(new ForgotPasswordSendCode($user, $user->code));

            return true;
        });
    }

    public function verifyCode(string $email, string $code): string
    {
        return DB::transaction(function () use ($email, $code): string {
            $user = User::where('email', $email)->first();

            if (!$user || $user->code != $code) {
                return 'invalid';
            }

            if ($user->expired_at < now()) {
                return 'expired';
            }

            return 'verified';
        });
    }

    public function changePassword(string $email, string $code, string $password): string
    {
        return DB::transaction(function () use ($email, $code, $password): string {
            $user = User::where('email', $email)->where('code', $code)->first();

            if (!$user) {
                return 'not_found';
            }

            if ($user->expired_at < now()) {
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
}
