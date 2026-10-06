<?php

namespace Tests\Feature;

use App\Models\Celebration;
use App\Models\CelebrationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CelebrationTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_registry_requires_admin_and_rejects_duplicate_or_invalid_names(): void
    {
        $id = CelebrationType::where('slug', 'other')->value('id');
        $this->postJson('/api/admin/celebration-types', ['name' => 'Ceremony'])->assertUnauthorized();
        $this->deleteJson('/api/admin/celebration-types/'.$id)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/admin/celebration-types', ['name' => 'Ceremony'])->assertForbidden();
        $this->deleteJson('/api/admin/celebration-types/'.$id)->assertForbidden();
        $this->actingAs($this->admin());
        foreach (['', ' ', 'BIRTHDAY', 'Degree-Advancement', str_repeat('x', 101)] as $name) {
            $this->postJson('/api/admin/celebration-types', ['name' => $name])->assertJsonValidationErrors('name');
        }
        $this->assertDatabaseCount('celebration_types', 5);
    }

    public function test_custom_type_can_be_used_and_only_deleted_when_unused(): void
    {
        $this->actingAs($this->admin());
        $id = $this->postJson('/api/admin/celebration-types', ['name' => 'Installation Ceremony'])
            ->assertCreated()->assertJsonPath('type.slug', 'installation_ceremony')->json('type.id');
        $payload = ['title' => 'New officers', 'category' => 'installation_ceremony', 'event_date' => today()->toDateString(), 'is_public' => true];
        $this->postJson('/api/admin/celebrations', $payload)->assertOk();
        $this->getJson('/api/public/pages/celebrations')->assertJsonPath('celebrations.0.category_label', 'Installation Ceremony');
        $this->getJson('/api/admin/celebrations')->assertJsonFragment(['slug' => 'installation_ceremony', 'celebrations_count' => 1]);
        $this->deleteJson('/api/admin/celebration-types/'.$id)->assertJsonValidationErrors('type');
        $celebration = Celebration::firstOrFail();
        $this->putJson('/api/admin/celebrations/'.$celebration->id, array_replace($payload, ['category' => 'fellowship']))->assertOk();
        $this->deleteJson('/api/admin/celebration-types/'.$id)->assertOk();
        $this->postJson('/api/admin/celebrations', $payload)->assertJsonValidationErrors('category');
        $this->assertDatabaseMissing('celebration_types', ['id' => $id]);
    }

    public function test_uploaded_greeting_and_gallery_images_are_saved_and_publicly_rendered(): void
    {
        $this->actingAs($this->admin());
        config(['lodge.cloudinary' => ['cloud_name' => 'test', 'api_key' => 'test', 'api_secret' => 'test']]);
        Http::fake(['*/image/upload' => Http::response(['public_id' => 'lodge/greeting', 'secure_url' => 'https://res.cloudinary.com/test/image/upload/greeting.png', 'resource_type' => 'image', 'width' => 100, 'height' => 100, 'bytes' => 100])]);
        $path = $this->post('/api/admin/media', ['file' => UploadedFile::fake()->image('greeting.png'), 'alt_text' => 'Birthday greeting'], ['Accept' => 'application/json'])->assertCreated()->json('media.path');
        $gallery = [['image' => $path, 'title' => 'Ceremony opening'], ['image' => '/images/lodge-ceremony.jpg', 'title' => 'With the brethren']];
        $this->postJson('/api/admin/celebrations', ['title' => 'Birthday greetings', 'category' => 'birthday', 'event_date' => today()->toDateString(), 'is_public' => true, 'image' => $path, 'gallery' => $gallery])->assertOk();
        $this->getJson('/api/public/pages/celebrations')->assertJsonPath('celebrations.0.image', $path)->assertJsonPath('celebrations.0.gallery', $gallery);
        $this->deleteJson('/api/admin/media/1')->assertJsonValidationErrors('media');
        Http::assertSentCount(1);
    }
}
