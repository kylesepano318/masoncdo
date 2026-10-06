<?php

namespace Tests\Feature;

use App\Models\LodgeApplication;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        $this->actingAs($user);
    }

    public function test_pages_without_dynamic_sections_skip_unrelated_queries(): void
    {
        Page::create(['name' => 'Simple', 'slug' => 'simple', 'is_published' => true, 'published_sections' => [['id' => 900, 'section_type' => 'rich_text', 'is_visible' => true, 'body' => 'Simple content']]]);
        DB::enableQueryLog();
        $response = $this->getJson('/api/public/pages/simple')->assertOk()->assertJsonPath('members', [])->assertJsonPath('affiliations', [])->assertJsonPath('celebrations', []);
        $this->assertMatchesRegularExpression('/^app;dur=[\d.]+, db;dur=[\d.]+, queries;desc="\d+"$/', $response->headers->get('Server-Timing'));
        $queries = collect(DB::getQueryLog())->pluck('query')->implode('\n');
        foreach (['members', 'membership_positions', 'affiliations', 'celebrations'] as $table) {
            $this->assertStringNotContainsString('from "'.$table.'"', $queries);
        }
        DB::disableQueryLog();
    }

    public function test_counts_use_one_application_query_and_dashboard_aggregates_member_counts(): void
    {
        $this->admin();
        DB::enableQueryLog();
        $this->getJson('/api/admin/applications/counts')->assertOk()->assertJson(['pending' => 0, 'unread' => 0, 'this_month' => 0]);
        $this->assertCount(1, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "applications"')));
        DB::flushQueryLog();
        $this->getJson('/api/admin/dashboard')->assertOk();
        $this->assertCount(1, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "members"')));
        $this->assertCount(2, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "applications"')));
        DB::disableQueryLog();
    }

    public function test_editor_media_is_bounded_and_older_images_remain_searchable(): void
    {
        $this->admin();
        $images = [];
        for ($i = 1; $i <= 60; $i++) {
            $images[] = ['filename' => $i.'.png', 'original_name' => $i === 1 ? 'older-greeting.png' : 'photo-'.$i.'.png', 'path' => '/images/'.$i.'.png', 'mime_type' => 'image/png', 'size' => 100, 'alt_text' => $i === 1 ? 'Historic greeting' : 'Photo '.$i, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('media')->insert($images);
        $this->getJson('/api/admin/celebrations')->assertJsonCount(24, 'media')->assertJsonPath('media.0.original_name', 'photo-60.png');
        $this->getJson('/api/admin/settings/notifications')->assertJsonPath('media', []);
        $this->getJson('/api/admin/media?page=2')->assertJsonCount(24, 'media.data')->assertJsonPath('media.last_page', 3);
        $this->getJson('/api/admin/media?search=Historic')->assertJsonCount(1, 'media.data')->assertJsonPath('media.data.0.original_name', 'older-greeting.png');
    }

    public function test_review_saves_status_and_notes_together_and_validates_before_writing(): void
    {
        config(['lodge.notification_email' => 'lodge@example.test']);
        $data = ['first_name' => 'Review', 'last_name' => 'Test', 'date_of_birth' => '1990-01-01', 'complete_address' => 'Address', 'city' => 'CDO', 'province' => 'Misamis Oriental', 'mobile_number' => '09123456789', 'email' => 'applicant@example.test', 'reason_for_joining' => 'Service', 'has_referrer' => false, 'declaration' => true, 'consent' => true];
        $this->postJson('/api/applications', $data)->assertCreated();
        $application = LodgeApplication::firstOrFail();
        $this->admin();
        $url = '/api/admin/applications/'.$application->id.'/status';
        $this->patchJson($url, ['status' => 'approved', 'admin_notes' => str_repeat('x', 10001)])->assertJsonValidationErrors('admin_notes');
        $this->assertSame('pending', $application->fresh()->status);
        $this->patchJson($url, ['status' => 'under_review', 'admin_notes' => 'Private review'])->assertOk();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'under_review', 'admin_notes' => 'Private review']);
    }
}
