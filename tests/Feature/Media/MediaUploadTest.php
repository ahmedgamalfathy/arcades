<?php

namespace Tests\Feature\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use InteractsWithTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantTesting();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantTesting();
        parent::tearDown();
    }

    public function test_user_can_upload_media_file(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_medias']);

        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/media', [
            'path' => UploadedFile::fake()->image('avatar.jpg'),
            'type' => 1,
            'category' => 'avatars',
        ])->assertOk();

        $path = $this->getUploadedPath($tenant);

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('media', ['category' => 'avatars'], 'tenant');
    }

    public function test_upload_media_rejects_non_image_file(): void
    {
        $tenant = $this->createTenantDatabase();
        $user = $this->createUser($tenant, ['create_medias']);

        $this->actingAsUser($user);

        $this->postJson('/api/v1/admin/media', [
            'path' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
            'category' => 'avatars',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('media', 0, 'tenant');
    }

    private function getUploadedPath(string $tenant): ?string
    {
        $this->useTenantDatabase($tenant);

        return \App\Models\Media\Media::query()->first()?->getRawOriginal('path');
    }
}
