<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Mail\ForgotPasswordSendCode;
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

    public function test_send_code_does_not_reveal_whether_email_exists(): void
    {
        Mail::fake();

        User::create([
            'name' => 'Reset User',
            'email' => 'reset@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
        ]);

        $knownResponse = $this->postJson('/api/v1/admin/forgot-password/send-code', [
            'email' => 'reset@example.com',
        ]);
        $unknownResponse = $this->postJson('/api/v1/admin/forgot-password/send-code', [
            'email' => 'missing@example.com',
        ]);

        $knownResponse->assertOk();
        $unknownResponse->assertOk();
        $this->assertSame($knownResponse->json(), $unknownResponse->json());
        Mail::assertSentCount(1);
    }

    public function test_verify_code_rejects_wrong_code(): void
    {
        $user = User::create([
            'name' => 'Reset User',
            'email' => 'reset@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
            'code' => Hash::make('123456'),
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
            'code' => Hash::make('123456'),
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
        $this->assertTrue(Hash::check('123456', $user->code));
    }

    public function test_reset_code_is_hashed_and_valid_flow_changes_password(): void
    {
        Mail::fake();
        $user = User::create([
            'name' => 'Reset User',
            'email' => 'secure-reset@example.com',
            'password' => Hash::make('password1'),
            'is_active' => 1,
        ]);

        $this->postJson('/api/v1/admin/forgot-password/send-code', [
            'email' => $user->email,
        ])->assertOk();

        $plainCode = null;
        Mail::assertSent(ForgotPasswordSendCode::class, function (ForgotPasswordSendCode $mail) use (&$plainCode): bool {
            $plainCode = (string) $mail->code;

            return true;
        });

        $user->refresh();
        $this->assertNotNull($plainCode);
        $this->assertNotSame($plainCode, $user->code);
        $this->assertTrue(Hash::check($plainCode, $user->code));

        $this->postJson('/api/v1/admin/forgot-password/verify-code', [
            'email' => $user->email,
            'code' => $plainCode,
        ])->assertOk();

        $this->postJson('/api/v1/admin/forgot-password/change-password', [
            'email' => $user->email,
            'code' => $plainCode,
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newpass1', $user->password));
        $this->assertNull($user->code);
        $this->assertNull($user->expired_at);
    }

    public function test_password_reset_send_endpoint_is_rate_limited(): void
    {
        Mail::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/admin/forgot-password/send-code', [
                'email' => 'limited@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/v1/admin/forgot-password/send-code', [
            'email' => 'limited@example.com',
        ])->assertStatus(429);
    }

    public function test_verify_and_change_password_share_attempt_rate_limit(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/admin/forgot-password/verify-code', [
                'email' => 'attempts@example.com',
                'code' => '123456',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/admin/forgot-password/change-password', [
            'email' => 'attempts@example.com',
            'code' => '123456',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertStatus(429);
    }

    public function test_legacy_plaintext_reset_code_is_rejected_safely(): void
    {
        $user = User::create([
            'name' => 'Legacy Reset User',
            'email' => 'legacy-reset@example.com',
            'password' => Hash::make('password1'),
            'is_active' => 1,
            'code' => '123456',
            'expired_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/v1/admin/forgot-password/verify-code', [
            'email' => $user->email,
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_collation_equivalent_email_cannot_receive_another_users_code(): void
    {
        Mail::fake();
        $user = User::create([
            'name' => 'Victim User',
            'email' => 'user@example.com',
            'password' => Hash::make('password1'),
            'is_active' => 1,
        ]);

        $this->postJson('/api/v1/admin/forgot-password/send-code', [
            'email' => 'usér@example.com',
        ])->assertOk();

        $user->refresh();
        $this->assertNull($user->code);
        $this->assertNull($user->expired_at);
        Mail::assertNothingSent();
    }
}
