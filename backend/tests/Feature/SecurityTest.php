<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_errors_are_json_without_an_accept_header(): void
    {
        $this->get('/api/admin/dashboard')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $this->get('/api/public/pages/missing')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeaders(['Origin' => 'http://localhost']);
        $this->seed();
        Mail::fake();
        config(['lodge.notification_email' => 'lodge@example.test', 'lodge.cloudinary' => ['cloud_name' => 'test', 'api_key' => 'test', 'api_secret' => 'test']]);
        Http::fake(['*/image/upload' => Http::response(['public_id' => 'lodge/test-image', 'secure_url' => 'https://res.cloudinary.com/test/image/upload/test-image.jpg', 'resource_type' => 'image', 'width' => 100, 'height' => 100, 'bytes' => 100]), '*/image/destroy' => Http::response(['result' => 'ok'])]);
    }

    public function test_database_allows_multiple_administrators(): void
    {
        $first = User::factory()->create();
        $first->is_admin = true;
        $first->save();
        $second = User::factory()->create();
        $second->is_admin = true;
        $second->save();
        $this->assertSame(2, User::where('is_admin', true)->count());
    }

    public function test_video_sections_require_a_supported_youtube_link(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        $page = Page::where('slug', 'home')->firstOrFail();
        $endpoint = '/api/admin/pages/'.$page->id.'/sections';
        $payload = ['section_type' => 'video', 'is_visible' => true, 'settings' => []];
        $this->postJson($endpoint, $payload)->assertJsonValidationErrors('settings.video_url');
        foreach (['https://example.com/embed/uACxhycnzC0', 'https://youtube.com.evil.test/watch?v=uACxhycnzC0', 'https://www.youtube.com/watch?v[]=uACxhycnzC0'] as $url) {
            $payload['settings']['video_url'] = $url;
            $this->postJson($endpoint, $payload)->assertJsonValidationErrors('settings.video_url');
        }
        foreach (['https://www.youtube.com/watch?v=uACxhycnzC0&t=7s', 'https://youtu.be/jsAHUOoD4Ns', 'https://www.youtube-nocookie.com/embed/jsAHUOoD4Ns'] as $url) {
            $payload['settings']['video_url'] = $url;
            $this->postJson($endpoint, $payload)->assertSuccessful();
        }
    }

    public function test_published_json_media_reference_is_protected_from_deletion(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin);
        $media = Media::create(['filename' => 'unique-test-image.jpg', 'original_name' => 'image.jpg', 'disk' => 'public', 'path' => '/storage/media/unique-test-image.jpg', 'mime_type' => 'image/jpeg', 'size' => 100, 'alt_text' => 'Protected photograph']);
        $page = Page::where('slug', 'home')->firstOrFail();
        $page->update(['published_sections' => [['section_type' => 'gallery', 'is_visible' => true, 'settings' => ['items' => [['image' => $media->path]]]]]]);
        $this->deleteJson('/api/admin/media/'.$media->id)->assertJsonValidationErrors('media');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
}
