<?php

namespace Tests\Feature\Notification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['auth_read_notification']);
        $notificationId = $this->createNotification($tenant, $user->id);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/admin/notifications/auth_read_notifications/{$notificationId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull(
            DB::connection('tenant')->table('notifications')->where('id', $notificationId)->value('read_at')
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['auth_read_notification']);
        $otherUser = $this->createUser($tenant, [], 'other@example.com');
        $notificationId = $this->createNotification($tenant, $otherUser->id);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/admin/notifications/auth_read_notifications/{$notificationId}")
            ->assertNotFound();

        $this->assertNull(
            DB::connection('tenant')->table('notifications')->where('id', $notificationId)->value('read_at')
        );
    }

    public function test_user_can_delete_own_notification(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['auth_delete_notification']);
        $notificationId = $this->createNotification($tenant, $user->id);

        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/admin/notifications/auth_delete_notifications/{$notificationId}")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notificationId], 'tenant');
    }

    private function createNotification(string $tenant, int $userId): string
    {
        $this->useTenantDatabase($tenant);

        $id = (string) Str::uuid();

        DB::connection('tenant')->table('notifications')->insert([
            'id' => $id,
            'type' => 'test',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'data' => json_encode(['message' => 'Timer expired']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
