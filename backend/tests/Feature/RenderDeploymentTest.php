<?php

namespace Tests\Feature;

use App\Jobs\DeliverApplicationEmail;
use App\Mail\ApplicationAcknowledgment;
use App\Mail\NewMembershipApplication;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ApplicationNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RenderDeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::preventStrayRequests();
    }

    private function r2(): void
    {
        config(['lodge.media_disk' => 'r2', 'filesystems.disks.r2.key' => 'test-key',
            'filesystems.disks.r2.secret' => 'test-secret', 'filesystems.disks.r2.bucket' => 'test',
            'filesystems.disks.r2.endpoint' => 'https://test.r2.cloudflarestorage.com',
            'filesystems.disks.r2.url' => 'https://media.example.test']);
        Storage::fake('r2', ['url' => 'https://media.example.test']);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
    }

    public function test_r2_upload_records_unique_object_keys_and_deletes_unreferenced_files(): void
    {
        $this->r2();
        $response = $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('same-name.png'), 'alt_text' => 'Greeting'])->assertCreated();
        $first = Media::findOrFail($response->json('media.id'));
        $this->assertSame('r2', $first->disk);
        $this->assertStringStartsWith('https://media.example.test/images/', $first->path);
        Storage::disk('r2')->assertExists($first->filename);
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('same-name.png'), 'alt_text' => 'Second'])->assertCreated();
        $this->assertNotSame($first->filename, Media::latest('id')->first()->filename);
        $this->deleteJson('/api/admin/media/'.$first->id)->assertOk();
        Storage::disk('r2')->assertMissing($first->filename);
        $this->assertDatabaseMissing('media', ['id' => $first->id]);
    }

    public function test_r2_configuration_and_upload_failures_do_not_create_media_records(): void
    {
        $this->r2();
        config(['filesystems.disks.r2.key' => null]);
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('image.png'), 'alt_text' => 'Image'])->assertUnprocessable();
        $this->assertDatabaseCount('media', 0);
        config(['filesystems.disks.r2.key' => 'key']);
        Storage::shouldReceive('disk')->with('r2')->andReturnSelf();
        Storage::shouldReceive('put')->once()->andThrow(new \RuntimeException('Provider secret error'));
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('image.png'), 'alt_text' => 'Image'])->assertUnprocessable()->assertDontSee('Provider secret error');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_r2_failed_delete_keeps_the_database_record(): void
    {
        $this->r2();
        $media = Media::create(['disk' => 'r2', 'filename' => 'images/test.png', 'path' => 'https://media.example.test/images/test.png', 'original_name' => 'test.png', 'mime_type' => 'image/png', 'size' => 10, 'alt_text' => 'Test']);
        Storage::shouldReceive('disk')->with('r2')->andReturnSelf();
        Storage::shouldReceive('delete')->once()->andThrow(new \RuntimeException('Failed'));
        $this->deleteJson('/api/admin/media/'.$media->id)->assertUnprocessable();
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    private function application(): array
    {
        return ['first_name' => 'Applicant', 'last_name' => 'Example', 'date_of_birth' => '1990-01-01',
            'complete_address' => 'Private street', 'city' => 'CDO', 'province' => 'Misamis Oriental',
            'mobile_number' => '09123456789', 'email' => 'applicant@example.test', 'reason_for_joining' => 'Service',
            'has_referrer' => false, 'declaration' => true, 'consent' => true];
    }

    public function test_pdf_uploads_are_documents_and_are_excluded_from_image_pickers(): void
    {
        $this->r2();
        $file = UploadedFile::fake()->createWithContent('announcement.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $response = $this->postJson('/api/admin/media', ['file' => $file, 'alt_text' => 'Announcement'])->assertCreated();
        $media = Media::findOrFail($response->json('media.id'));
        $this->assertSame('raw', $media->resource_type);
        $this->assertNull($media->width);
        $this->assertStringStartsWith('documents/', $media->filename);
        $this->assertCount(0, Media::pickerItems());
        $this->getJson('/api/admin/media?type=image')->assertOk()->assertJsonCount(0, 'media.data');
        $this->getJson('/api/admin/media')->assertOk()->assertJsonPath('media.data.0.mime_type', 'application/pdf');
        SiteSetting::create(['key' => 'document_test', 'value' => ['body' => '<a href="'.$media->path.'">Announcement</a>']]);
        $this->deleteJson('/api/admin/media/'.$media->id)->assertUnprocessable();
        Storage::disk('r2')->assertExists($media->filename);
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->create('script.html', 1, 'text/html'), 'alt_text' => 'Bad'])->assertUnprocessable();
    }

    private function notifications(): void
    {
        SiteSetting::updateOrCreate(['key' => 'notifications'], ['value' => [
            'application_notification_email' => 'admin@example.test',
            'send_application_notification_email' => true, 'send_applicant_confirmation_email' => true,
        ]]);
    }

    public function test_mailtrap_https_sends_both_recipients_and_their_reference(): void
    {
        $this->notifications();
        config(['mail.default' => 'mailtrap_api', 'mail.from.address' => 'applications@example.test', 'mail.mailers.mailtrap_api.token' => 'test-token']);
        app('mail.manager')->purge('mailtrap_api');
        Http::fake(['send.api.mailtrap.io/api/send' => Http::response(['success' => true, 'message_ids' => ['test-message']])]);
        $response = $this->postJson('/api/applications', $this->application())->assertCreated();
        foreach (['admin@example.test', 'applicant@example.test'] as $recipient) {
            Http::assertSent(fn ($r) => $r->url() === 'https://send.api.mailtrap.io/api/send'
                && $r->hasHeader('Authorization', 'Bearer test-token') && $r['to'][0]['email'] === $recipient
                && str_contains($r['html'], $response->json('reference_number')));
        }
        Http::assertSentCount(2);
        $this->assertNotNull(DB::table('applications')->value('acknowledgment_email_sent_at'));
    }

    public function test_database_queue_returns_reference_before_sending_and_tracks_both_jobs(): void
    {
        $this->notifications();
        config(['queue.default' => 'database']);
        Mail::fake();
        $this->postJson('/api/applications', $this->application())->assertCreated();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('jobs', 2);
        $id = DB::table('applications')->value('id');
        $service = app(ApplicationNotificationService::class);
        foreach (['admin', 'applicant'] as $kind) {
            (new DeliverApplicationEmail($id, $kind))->handle($service);
            // A successfully processed duplicate job does not send again.
            (new DeliverApplicationEmail($id, $kind))->handle($service);
        }
        Mail::assertSent(NewMembershipApplication::class, 1);
        Mail::assertSent(ApplicationAcknowledgment::class, 1);
    }

    public function test_failed_mailtrap_delivery_keeps_application_and_job_throws_for_retry(): void
    {
        $this->notifications();
        config(['queue.default' => 'database', 'mail.default' => 'mailtrap_api', 'mail.from.address' => 'applications@example.test', 'mail.mailers.mailtrap_api.token' => 'test-token']);
        app('mail.manager')->purge('mailtrap_api');
        Http::fake(['send.api.mailtrap.io/api/send' => Http::response(['success' => false, 'errors' => ['secret-provider-detail']], 403)]);
        $this->postJson('/api/applications', $this->application())->assertCreated();
        $id = DB::table('applications')->value('id');
        try {
            (new DeliverApplicationEmail($id, 'admin'))->handle(app(ApplicationNotificationService::class));
            $this->fail('Delivery failure must trigger a queue retry.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('secret-provider-detail', $e->getMessage());
        }
        $this->assertDatabaseCount('applications', 1);
        $this->assertNotNull(DB::table('applications')->value('notification_email_failed_at'));
    }

    public function test_browser_deep_links_serve_spa_but_missing_api_paths_remain_404(): void
    {
        $path = public_path('index.html');
        $original = is_file($path) ? file_get_contents($path) : null;
        file_put_contents($path, '<html>SPA deployment test</html>');
        try {
            $this->get('/admin/login')->assertOk()->assertHeader('Cache-Control', 'no-cache, public');
            $this->get('/celebrations')->assertOk();
            $this->getJson('/api/does-not-exist')->assertNotFound();
            $this->get('/assets/missing.js')->assertNotFound();
            $this->get('/sanctum/does-not-exist')->assertNotFound();
        } finally {
            if ($original !== null) {
                file_put_contents($path, $original);
            } else {
                unlink($path);
            }
        }
    }
}
