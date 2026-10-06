<?php

namespace Tests\Feature;

use App\Models\Celebration;
use App\Models\Media;
use App\Models\Member;
use App\Models\MembershipPosition;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\MemberService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LodgeTest extends TestCase
{
    use RefreshDatabase;

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

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_public_session_reports_only_the_current_administrator_and_is_not_cacheable(): void
    {
        $this->getJson('/api/public/session')->assertOk()->assertExactJson(['user' => null])
            ->assertHeader('Cache-Control', 'no-store, private');
        $member = User::factory()->create();
        $this->actingAs($member)->getJson('/api/public/session')->assertOk()->assertExactJson(['user' => null]);
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/public/session')->assertOk()
            ->assertExactJson(['user' => $admin->only('id', 'name', 'email')]);
    }

    private function memberData(int $position = 4): array
    {
        return ['first_name' => 'Juan', 'last_name' => 'Example', 'membership_position_id' => MembershipPosition::where('rank', $position)->firstOrFail()->id, 'status' => 'active', 'is_public' => true, 'display_order' => 0];
    }

    private function applicationData(): array
    {
        return ['first_name' => 'Applicant', 'last_name' => 'Example', 'date_of_birth' => '1990-01-01', 'complete_address' => 'Private address', 'city' => 'Cagayan de Oro', 'province' => 'Misamis Oriental', 'mobile_number' => '09123456789', 'email' => 'applicant@example.test', 'reason_for_joining' => 'Serve the community', 'has_referrer' => false, 'declaration' => true, 'consent' => true];
    }

    public function test_admin_routes_require_authentication_and_admin_authorization(): void
    {
        foreach (['/api/admin/dashboard', '/api/admin/members', '/api/admin/applications', '/api/admin/media', '/api/admin/celebrations', '/api/admin/pages/'.Page::where('slug', 'home')->firstOrFail()->id] as $url) {
            $this->getJson($url)->assertUnauthorized();
        } $this->actingAs(User::factory()->create())->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_admin_login_logout_and_invalid_credentials(): void
    {
        $admin = $this->admin();
        $admin->update(['password' => 'Secure-test-password!42']);
        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertJsonValidationErrors('email');
        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Secure-test-password!42', 'remember' => true])->assertOk();
        $this->assertAuthenticatedAs($admin);
        $this->postJson('/api/admin/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_registration_is_unavailable(): void
    {
        $this->getJson('/register')->assertNotFound();
        $this->postJson('/register')->assertNotFound();
    }

    public function test_member_crud_and_photo_upload(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $data = $this->memberData() + ['photo' => UploadedFile::fake()->image('portrait.jpg')];
        $this->postJson('/api/admin/members', $data)->assertSuccessful();
        $member = Member::first();
        $this->assertNotNull($member);
        $this->assertStringStartsWith('https://res.cloudinary.com/', $member->profile_photo);
        $this->putJson('/api/admin/members/'.$member->id, $this->memberData() + ['biography' => 'Updated biography'])->assertSuccessful();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'biography' => 'Updated biography']);
        $this->deleteJson('/api/admin/members/'.$member->id)->assertOk();
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
    }

    public function test_all_three_officer_positions_require_confirmation_and_replace_transactionally(): void
    {
        $this->actingAs($this->admin());
        foreach ([1, 2, 3] as $position) {
            $this->postJson('/api/admin/members', $this->memberData($position))->assertSuccessful();
            $old = Member::where('active_officer_position', MembershipPosition::where('rank', $position)->firstOrFail()->id)->first();
            $data = array_replace($this->memberData($position), ['first_name' => 'Replacement']);
            $this->postJson('/api/admin/members', $data)->assertJsonValidationErrors('replace_officer');
            $this->assertSame(1, Member::where('active_officer_position', MembershipPosition::where('rank', $position)->firstOrFail()->id)->count());
            $this->postJson('/api/admin/members', $data + ['replace_officer' => true])->assertSuccessful();
            $this->assertSame('past_officer', $old->fresh()->status);
            $this->assertSame(1, Member::where('active_officer_position', MembershipPosition::where('rank', $position)->firstOrFail()->id)->count());
        }
    }

    public function test_database_rejects_duplicate_active_officers(): void
    {
        app(MemberService::class)->save($this->memberData(1));
        $this->expectException(QueryException::class);
        Member::create($this->memberData(1) + ['active_officer_position' => MembershipPosition::where('rank', 1)->firstOrFail()->id]);
    }

    public function test_multiple_normal_members_and_public_visibility(): void
    {
        app(MemberService::class)->save($this->memberData());
        app(MemberService::class)->save(array_replace($this->memberData(), ['first_name' => 'Private', 'is_public' => false]));
        app(MemberService::class)->save(array_replace($this->memberData(), ['first_name' => 'Inactive', 'status' => 'inactive']));
        $this->getJson('/api/public/members')->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'Juan Example')->assertJsonMissingPath('data.0.member_number');
    }

    public function test_application_submission_generates_sequential_server_reference(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6));
        $this->postJson('/api/applications', $this->applicationData())->assertCreated()->assertJsonPath('reference_number', 'APP-2026-000001');
        $this->postJson('/api/applications', $this->applicationData())->assertJsonPath('reference_number', 'APP-2026-000002');
        $this->assertDatabaseCount('applications', 2);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->postJson('/api/applications', $this->applicationData())->assertJsonPath('reference_number', 'APP-2027-000001');
    }

    public function test_application_requires_fields_consent_and_referral_details(): void
    {
        $this->postJson('/api/applications', [])->assertJsonValidationErrors(['first_name', 'date_of_birth', 'consent']);
        $this->postJson('/api/applications', array_replace($this->applicationData(), ['consent' => false, 'has_referrer' => true]))->assertJsonValidationErrors(['consent', 'referring_member_name']);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_applications_are_private_admin_can_review_and_convert_only_once(): void
    {
        $a = app(ApplicationService::class)->submit($this->applicationData());
        $this->getJson('/api/admin/applications/'.$a->id)->assertUnauthorized();
        $this->getJson('/api/public/pages/home')->assertJsonMissingPath('applications');
        $this->actingAs($this->admin())->getJson('/api/admin/applications/'.$a->id)->assertJsonPath('application.complete_address', 'Private address');
        $this->patchJson('/api/admin/applications/'.$a->id.'/status', ['status' => 'under_review', 'admin_notes' => 'Private notes'])->assertSuccessful();
        $this->postJson('/api/admin/applications/'.$a->id.'/convert-to-member')->assertSuccessful();
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->converted_at);
        $member = Member::find($a->fresh()->converted_member_id);
        $this->assertFalse($member->is_public);
        $this->assertSame(MembershipPosition::where('slug', 'member')->firstOrFail()->id, $member->membership_position_id);
        $this->postJson('/api/admin/applications/'.$a->id.'/convert-to-member')->assertJsonValidationErrors('conversion');
        $this->assertDatabaseCount('members', 1);
        $this->deleteJson('/api/admin/members/'.$member->id);
        $this->postJson('/api/admin/applications/'.$a->id.'/convert-to-member')->assertJsonValidationErrors('conversion');
    }

    public function test_application_approval_and_rejection(): void
    {
        $a = app(ApplicationService::class)->submit($this->applicationData());
        $this->actingAs($this->admin())->patchJson('/api/admin/applications/'.$a->id.'/status', ['status' => 'rejected', 'admin_notes' => 'Reviewed'])->assertSuccessful();
        $this->assertSame('rejected', $a->fresh()->status);
        $this->patchJson('/api/admin/applications/'.$a->id.'/status', ['status' => 'approved'])->assertSuccessful();
        $this->assertSame('approved', $a->fresh()->status);
    }

    public function test_cms_draft_preview_publish_visibility_reorder_and_sanitization(): void
    {
        $page = Page::where('slug', 'home')->first();
        $section = $page->sections()->first();
        $this->actingAs($this->admin());
        $this->putJson('/api/admin/sections/'.$section->id, ['section_type' => 'hero', 'title' => 'Draft heading', 'body' => '<p>Hello</p><script>alert(1)</script><a href="javascript:alert(1)">Unsafe</a>', 'is_visible' => false])->assertSuccessful();
        $this->assertStringNotContainsString('<script', $section->fresh()->body);
        $this->assertStringNotContainsString('javascript:', $section->fresh()->body);
        $this->getJson('/api/public/pages/home')->assertJsonPath('sections.0.title', 'S:.I:.G:.L:.O:.');
        $this->getJson('/api/admin/preview/home')->assertJsonPath('sections.0.section_type', 'lodge_feature');
        $ids = $page->sections()->pluck('id')->reverse()->values()->all();
        $this->postJson('/api/admin/pages/'.$page->id.'/reorder', ['ids' => $ids])->assertSuccessful();
        $this->assertSame($ids[0], $page->sections()->first()->id);
        $this->putJson('/api/admin/pages/'.$page->id, ['name' => 'Home', 'publish' => true])->assertSuccessful();
        $this->getJson('/api/public/pages/home')->assertJsonPath('sections.0.section_type', 'cta');
    }

    public function test_cms_add_duplicate_delete_and_cross_page_reorder_rejected(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/pages/'.Page::where('slug', 'home')->firstOrFail()->id.'/sections', ['section_type' => 'quote', 'title' => 'New quotation', 'is_visible' => true])->assertSuccessful();
        $section = PageSection::latest('id')->first();
        $this->postJson('/api/admin/sections/'.$section->id.'/duplicate')->assertOk();
        $this->assertDatabaseHas('page_sections', ['title' => 'New quotation (copy)']);
        $this->deleteJson('/api/admin/sections/'.$section->id)->assertOk();
        $this->postJson('/api/admin/pages/'.Page::where('slug', 'home')->firstOrFail()->id.'/reorder', ['ids' => [PageSection::where('page_id', Page::where('slug', 'history')->firstOrFail()->id)->first()->id]])->assertStatus(422);
    }

    public function test_image_upload_security_metadata_and_referenced_deletion(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'), 'alt_text' => 'Bad'])->assertJsonValidationErrors('file');
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('safe.png'), 'alt_text' => 'Safe image'])->assertSuccessful();
        $media = Media::first();
        $this->assertNotSame('safe.png', $media->filename);
        $this->putJson('/api/admin/media/'.$media->id, ['alt_text' => 'Edited', 'caption' => 'Caption'])->assertSuccessful();
        $this->assertSame('Edited', $media->fresh()->alt_text);
        Celebration::create(['title' => 'Photo reference', 'event_date' => today(), 'image' => $media->path, 'category' => 'birthday', 'is_public' => true]);
        $this->deleteJson('/api/admin/media/'.$media->id)->assertJsonValidationErrors('media');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Celebration::query()->delete();
        $this->deleteJson('/api/admin/media/'.$media->id)->assertSuccessful();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_celebration_visibility_current_year_and_year_rollover(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6));
        foreach ([['Current birthday', '2026-12-20', true], ['Private birthday', '2026-11-01', false], ['Next December', '2027-12-01', true], ['Past moment', '2025-05-01', true]] as [$title,$date,$public]) {
            Celebration::create(['title' => $title, 'category' => 'birthday', 'event_date' => $date, 'is_public' => $public]);
        } $this->getJson('/api/public/pages/celebrations')->assertJsonCount(2, 'celebrations')->assertJsonPath('celebrations.1.title', 'Current birthday');
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->getJson('/api/public/pages/celebrations')->assertJsonCount(3, 'celebrations')->assertJsonPath('celebrations.2.title', 'Next December');
    }

    public function test_celebrations_crud_default_public_and_private_override(): void
    {
        $this->actingAs($this->admin());
        $data = ['title' => 'Birthday celebration', 'category' => 'birthday', 'event_date' => '2026-12-01', 'is_public' => true, 'gallery' => []];
        $this->postJson('/api/admin/celebrations', $data)->assertSuccessful();
        $c = Celebration::first();
        $this->assertTrue($c->is_public);
        $this->putJson('/api/admin/celebrations/'.$c->id, array_replace($data, ['is_public' => false]))->assertSuccessful();
        $this->assertFalse($c->fresh()->is_public);
        $this->deleteJson('/api/admin/celebrations/'.$c->id)->assertOk();
        $this->assertDatabaseCount('celebrations', 0);
    }

    public function test_admin_screens_and_theme_branding_settings(): void
    {
        $this->actingAs($this->admin());
        foreach (['/api/admin/dashboard', '/api/admin/members', '/api/admin/members/create', '/api/admin/applications', '/api/admin/celebrations', '/api/admin/affiliations', '/api/admin/media', '/api/admin/pages/'.Page::where('slug', 'home')->firstOrFail()->id, '/api/admin/settings/branding', '/api/admin/settings/theme', '/api/admin/settings/navigation', '/api/admin/settings/account'] as $url) {
            $this->getJson($url)->assertOk();
        } $this->putJson('/api/admin/settings/branding', ['name' => 'Golden Friendship', 'location' => 'CDO', 'emblem' => '/images/lodge-brethren.jpg'])->assertSuccessful();
        $this->getJson('/api/public/site')->assertJsonPath('site.branding.emblem', '/images/lodge-brethren.jpg');
    }
}
