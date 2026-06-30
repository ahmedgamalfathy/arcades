<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ForgotPasswordNegativeTest extends TestCase
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

    public function test_send_code_rejects_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/admin/forgot-password/send-code', [
            'email' => 'missing@example.com',
        ])->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function test_verify_code_rejects_wrong_code(): void
    {
        $user = User::create([
            'name' => 'Reset User',
            'email' => 'reset@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
            'code' => '123456',
            'expired_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/v1/admin/forgot-password/verify-code', [
            'email' => $user->email,
            'code' => '654321',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_change_password_rejects_expired_code_and_keeps_old_password(): void
    {
        $user = User::create([
            'name' => 'Expired Reset User',
            'email' => 'expired-reset@example.com',
            'password' => Hash::make('password1'),
            'is_active' => 1,
            'code' => '123456',
            'expired_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/admin/forgot-password/change-password', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertUnprocessable();

        $user->refresh();

        $this->assertTrue(Hash::check('password1', $user->password));
        $this->assertSame('123456', $user->code);
    }
}
