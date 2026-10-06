<?php

namespace Tests\Feature;

use App\Mail\ApplicationAcknowledgment;
use App\Mail\NewMembershipApplication;
use App\Models\LodgeApplication;
use App\Models\Member;
use App\Models\MembershipPosition;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['lodge.notification_email' => 'lodge@example.test']);
    }

    private function data(): array
    {
        return ['first_name' => 'Applicant', 'last_name' => 'Example', 'date_of_birth' => '1990-01-01', 'complete_address' => 'PRIVATE ADDRESS', 'city' => 'CDO', 'province' => 'Misamis Oriental', 'mobile_number' => '09123456789', 'email' => 'applicant@example.test', 'reason_for_joining' => 'Service', 'has_referrer' => false, 'declaration' => true, 'consent' => true];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    public function test_submission_commits_before_notification_and_exposes_only_reference(): void
    {
        $checked = false;
        $baseline = DB::transactionLevel();
        Mail::shouldReceive('to')->once()->with('lodge@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($mail) use (&$checked, $baseline) {
            $this->assertInstanceOf(NewMembershipApplication::class, $mail);
            $this->assertSame($baseline, DB::transactionLevel());
            $this->assertDatabaseCount('applications', 1);
            $checked = true;
        });
        $response = $this->postJson('/api/applications', $this->data());
        $response->assertCreated()->assertJsonStructure(['message', 'reference_number'])->assertJsonMissingPath('id')->assertJsonMissingPath('email');
        $this->assertCount(2, $response->json());
        $this->assertTrue($checked);
        $a = LodgeApplication::first();
        $this->assertFalse($a->is_read_by_admin);
        $this->assertSame('pending', $a->status);
        $this->assertNotNull($a->notification_email_sent_at);
        $this->assertNotNull($a->certification_accepted_at);
        $this->assertNotNull($a->privacy_consent_accepted_at);
    }

    public function test_email_exception_never_discards_submission_or_leaks_credentials(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp://username:secret-password@example.test'));
        $this->postJson('/api/applications', $this->data())->assertCreated();
        $a = LodgeApplication::first();
        $this->assertNotNull($a->notification_email_failed_at);
        $this->assertStringNotContainsString('secret-password', $a->notification_email_error);
        $this->assertNull($a->notification_email_sent_at);
    }

    public function test_missing_recipient_is_a_recorded_failure_with_successful_submission(): void
    {
        config(['lodge.notification_email' => null]);
        Mail::fake();
        $this->postJson('/api/applications', $this->data())->assertCreated();
        $this->assertNotNull(LodgeApplication::first()->notification_email_failed_at);
        Mail::assertNothingSent();
    }

    public function test_acknowledgment_is_optional_and_contains_no_private_details(): void
    {
        Mail::fake();
        $this->postJson('/api/applications', $this->data())->assertCreated();
        Mail::assertNotSent(ApplicationAcknowledgment::class);
        SiteSetting::updateOrCreate(['key' => 'notifications'], ['value' => ['application_notification_email' => 'new-lodge@example.test', 'send_applicant_confirmation_email' => true]]);
        $this->postJson('/api/applications', $this->data())->assertCreated();
        Mail::assertSent(ApplicationAcknowledgment::class, fn ($m) => $m->hasTo('applicant@example.test'));
        Mail::assertSent(NewMembershipApplication::class, fn ($m) => $m->hasTo('new-lodge@example.test'));
        $render = (new NewMembershipApplication(LodgeApplication::first()))->render();
        $this->assertStringNotContainsString('PRIVATE ADDRESS', $render);
        $this->assertStringNotContainsString('09123456789', $render);
    }

    public function test_dashboard_counts_unread_filter_mark_read_notes_and_resend(): void
    {
        Mail::fake();
        $this->postJson('/api/applications', $this->data())->assertCreated();
        $a = LodgeApplication::first();
        $this->actingAs($this->admin());
        $this->getJson('/api/admin/applications/counts')->assertJsonPath('unread', 1)->assertJsonPath('pending', 1)->assertJsonPath('this_month', 1);
        $this->getJson('/api/admin/applications?unread=1&search=applicant')->assertJsonCount(1, 'applications.data');
        $this->getJson('/api/admin/applications/'.$a->id)->assertJsonPath('application.is_read_by_admin', true);
        $this->getJson('/api/admin/applications/counts')->assertJsonPath('unread', 0);
        $this->patchJson('/api/admin/applications/'.$a->id.'/notes', ['admin_notes' => 'PRIVATE NOTES'])->assertOk();
        $this->postJson('/api/admin/applications/'.$a->id.'/resend-notification')->assertOk()->assertJsonPath('sent', true);
        $this->getJson('/api/public/site')->assertJsonMissingPath('site.notifications');
        $this->getJson('/api/public/pages/home')->assertJsonMissingPath('applications');
        $this->assertSame('PRIVATE NOTES', $a->fresh()->admin_notes);
    }

    public function test_honeypot_and_rate_limit(): void
    {
        Mail::fake();
        $this->postJson('/api/applications', $this->data() + ['website' => 'bot'])->assertUnprocessable();
        $this->assertDatabaseCount('applications', 0);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/applications', $this->data())->assertCreated();
        }$this->postJson('/api/applications', $this->data())->assertStatus(429);
    }

    public function test_notification_toggle_and_manual_override(): void
    {
        Mail::fake();
        SiteSetting::updateOrCreate(['key' => 'notifications'], ['value' => ['send_application_notification_email' => false, 'application_notification_email' => 'lodge@example.test']]);
        $this->postJson('/api/applications', $this->data())->assertCreated();
        Mail::assertNothingSent();
        $a = LodgeApplication::first();
        $this->assertNull($a->notification_email_failed_at);
        $this->actingAs($this->admin())->postJson('/api/admin/applications/'.$a->id.'/resend-notification')->assertOk()->assertJsonPath('sent', true);
        Mail::assertSent(NewMembershipApplication::class);
    }

    public function test_public_settings_are_allowlisted_and_test_email_is_admin_only(): void
    {
        SiteSetting::updateOrCreate(['key' => 'branding'], ['value' => ['name' => 'Lodge', 'mail_password' => 'secret', 'admin_email' => 'private@example.test']]);
        $this->getJson('/api/public/site')->assertJsonPath('site.branding.name', 'Lodge')->assertJsonMissingPath('site.branding.mail_password')->assertJsonMissingPath('site.branding.admin_email');
        $this->postJson('/api/admin/settings/notifications/test-email')->assertUnauthorized();
    }

    public function test_upload_failure_preserves_member_image(): void
    {
        config(['lodge.cloudinary' => ['cloud_name' => 'test', 'api_key' => 'key', 'api_secret' => 'secret']]);
        Http::fake(['*' => Http::response(['error' => 'failed'], 500)]);
        $position = MembershipPosition::where('slug', 'member')->first();
        $member = Member::create(['first_name' => 'Old', 'last_name' => 'Photo', 'membership_position_id' => $position->id, 'status' => 'active', 'profile_photo' => 'https://example.test/old.jpg', 'is_public' => true]);
        $this->actingAs($this->admin())->postJson('/api/admin/members/'.$member->id, ['_method' => 'put', 'first_name' => 'Old', 'last_name' => 'Photo', 'membership_position_id' => $position->id, 'status' => 'active', 'is_public' => true, 'display_order' => 0, 'photo' => UploadedFile::fake()->image('new.jpg')])->assertUnprocessable();
        $this->assertSame('https://example.test/old.jpg',$member->fresh()->profile_photo);
    }
}
