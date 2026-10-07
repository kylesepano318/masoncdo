<?php

namespace Tests\Feature;

use App\Mail\ApplicationAcknowledgment;
use App\Mail\NewMembershipApplication;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\AdministratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HostingerDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storage_upload_search_reference_protection_and_delete(): void
    {
        config(['lodge.media_disk' => 'public']);
        Storage::fake('public', ['url' => 'https://example.test/storage']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $response = $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('Greeting.png'), 'alt_text' => 'Birthday'])->assertCreated();
        $media = Media::findOrFail($response->json('media.id'));
        $this->assertSame('public', $media->disk);
        $this->assertStringStartsWith('https://example.test/storage/images/', $media->path);
        Storage::disk('public')->assertExists($media->filename);
        $this->getJson('/api/admin/media?search=greeting')->assertOk()->assertJsonCount(1, 'media.data');
        $setting = SiteSetting::create(['key' => 'greeting_test', 'value' => ['image' => $media->path]]);
        $this->deleteJson('/api/admin/media/'.$media->id)->assertUnprocessable();
        Storage::disk('public')->assertExists($media->filename);
        $setting->delete();
        $this->deleteJson('/api/admin/media/'.$media->id)->assertOk();
        Storage::disk('public')->assertMissing($media->filename);
        $file = UploadedFile::fake()->createWithContent('notice.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->postJson('/api/admin/media', ['file' => $file, 'alt_text' => 'Notice'])->assertCreated();
        $this->getJson('/api/admin/media?type=image')->assertOk()->assertJsonCount(0, 'media.data');
    }

    public function test_two_seeded_administrators_can_login_and_reseeding_preserves_changed_passwords(): void
    {
        $this->seedAccounts();
        try {
            $this->seed(AdministratorSeeder::class);
            $this->seed(AdministratorSeeder::class);
            $this->assertSame(2, User::where('is_admin', true)->count());
            config(['sanctum.stateful' => ['localhost']]);
            $this->withHeaders(['Origin' => 'http://localhost']);
            foreach (['masterTest', 'wardenTest'] as $username) {
                $this->postJson('/api/admin/login', ['email' => $username, 'password' => 'Initial-test!42'])->assertOk()->assertJsonPath('user.username', $username);
                $this->postJson('/api/admin/logout')->assertOk();
                app('auth')->forgetGuards();
            }
            $first = User::where('username', 'masterTest')->firstOrFail();
            $first->update(['password' => 'Changed-test!42']);
            $this->seed(AdministratorSeeder::class);
            $this->assertTrue(Hash::check('Changed-test!42', $first->fresh()->password));
            $this->assertNotSame('Initial-test!42', $first->fresh()->password);
        } finally {
            $this->clearAccounts();
        }
    }

    public function test_username_changes_require_password_and_do_not_affect_another_administrator(): void
    {
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeaders(['Origin' => 'http://localhost']);
        $first = User::factory()->create(['is_admin' => true, 'username' => 'first', 'password' => 'Initial-test!42']);
        $second = User::factory()->create(['is_admin' => true, 'username' => 'second']);
        $this->actingAs($first)->putJson('/api/admin/account', ['email' => $first->email, 'username' => 'second', 'current_password' => 'Initial-test!42'])->assertJsonValidationErrors('username');
        $this->putJson('/api/admin/account', ['email' => $first->email, 'username' => 'firstChanged', 'current_password' => 'wrong'])->assertJsonValidationErrors('current_password');
        $this->putJson('/api/admin/account', ['email' => $first->email, 'username' => 'firstChanged', 'current_password' => 'Initial-test!42'])->assertOk();
        $this->assertSame('second', $second->fresh()->username);
        $this->postJson('/api/admin/logout')->assertOk();
        $this->postJson('/api/admin/login', ['email' => 'firstChanged', 'password' => 'Initial-test!42'])->assertOk();
    }

    public function test_cron_sends_both_application_emails_and_exits_without_a_persistent_worker(): void
    {
        $this->seed();
        config(['queue.default' => 'database']);
        Mail::fake();
        SiteSetting::updateOrCreate(['key' => 'notifications'], ['value' => ['application_notification_email' => 'admin@example.test', 'send_application_notification_email' => true, 'send_applicant_confirmation_email' => true]]);
        $this->postJson('/api/applications', ['first_name' => 'Cron', 'last_name' => 'Test', 'date_of_birth' => '1990-01-01', 'complete_address' => 'Address', 'city' => 'CDO', 'province' => 'Misamis Oriental', 'mobile_number' => '09123456789', 'email' => 'applicant@example.test', 'reason_for_joining' => 'Service', 'has_referrer' => false, 'declaration' => true, 'consent' => true])->assertCreated();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('jobs', 2);
        $this->artisan('lodge:mail-queue')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertSent(NewMembershipApplication::class, 1);
        Mail::assertSent(ApplicationAcknowledgment::class, 1);
        $this->assertNotNull(DB::table('applications')->value('acknowledgment_email_sent_at'));
        $this->artisan('lodge:mail-queue')->assertSuccessful();
        Mail::assertSentCount(2);
    }

    public function test_cron_skips_an_overlapping_run_and_rejects_sync_configuration(): void
    {
        config(['queue.default' => 'database']);
        $lock = Cache::lock('lodge-mail-queue', 300);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('lodge:mail-queue')->expectsOutput('An earlier mail queue run is still active.')->assertSuccessful();
        } finally {
            $lock->release();
        }
        config(['queue.default' => 'sync']);
        $this->artisan('lodge:mail-queue')->assertFailed();
    }

    private function seedAccounts(): void
    {
        foreach ([1 => 'masterTest', 2 => 'wardenTest'] as $number => $username) {
            foreach (['USERNAME' => $username, 'PASSWORD' => 'Initial-test!42'] as $suffix => $value) {
                $key = "LODGE_ADMIN_{$number}_{$suffix}";
                $_ENV[$key] = $value;
            }
        }
    }

    private function clearAccounts(): void
    {
        foreach ([1, 2] as $number) {
            foreach (['USERNAME', 'PASSWORD'] as $suffix) {
                unset($_ENV["LODGE_ADMIN_{$number}_{$suffix}"]);
            }
        }
    }
}
