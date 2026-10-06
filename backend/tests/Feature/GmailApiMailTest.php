<?php

namespace Tests\Feature;

use App\Models\LodgeApplication;
use App\Models\SiteSetting;
use App\Services\ApplicationNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class GmailApiMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::preventStrayRequests();
        config([
            'mail.default' => 'gmail_api',
            'mail.from.address' => 'sender@gmail.com',
            'mail.mailers.gmail_api.client_id' => 'test-client',
            'mail.mailers.gmail_api.client_secret' => 'secret-client',
            'mail.mailers.gmail_api.refresh_token' => 'secret-refresh',
        ]);
        app('mail.manager')->purge('gmail_api');
    }

    public function test_gmail_sends_admin_alert_and_applicant_reference_over_https(): void
    {
        SiteSetting::updateOrCreate(['key' => 'notifications'], ['value' => [
            'application_notification_email' => 'admin@example.test',
            'send_application_notification_email' => true,
            'send_applicant_confirmation_email' => true,
        ]]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'secret-access', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'gmail-message-id']),
        ]);
        $response = $this->postJson('/api/applications', [
            'first_name' => 'Applicant', 'last_name' => 'Example', 'date_of_birth' => '1990-01-01',
            'complete_address' => 'Private street', 'city' => 'CDO', 'province' => 'Misamis Oriental',
            'mobile_number' => '09123456789', 'email' => 'applicant@example.test', 'reason_for_joining' => 'Service',
            'has_referrer' => false, 'declaration' => true, 'consent' => true,
        ])->assertCreated();
        $reference = $response->json('reference_number');
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token'
            && $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'secret-refresh');
        foreach (['admin@example.test', 'applicant@example.test'] as $recipient) {
            Http::assertSent(function (Request $r) use ($recipient, $reference) {
                if (! str_contains($r->url(), '/messages/send')) {
                    return false;
                }
                $raw = base64_decode(strtr($r['raw'], '-_', '+/'));

                return $r->hasHeader('Authorization', 'Bearer secret-access')
                    && str_contains($raw, 'To: '.$recipient) && str_contains($raw, $reference)
                    && str_contains($raw, 'sender@gmail.com');
            });
        }
        $this->assertNotNull(LodgeApplication::first()->notification_email_sent_at);
    }

    public function test_rejected_gmail_delivery_preserves_the_application_and_hides_provider_details(): void
    {
        SiteSetting::updateOrCreate(['key' => 'notifications'], ['value' => ['application_notification_email' => 'admin@example.test']]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'secret-access', 'expires_in' => 3600]),
            'gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'secret-provider-detail']], 403),
        ]);
        $record = LodgeApplication::create(['reference_number' => 'APP-2026-000099', 'first_name' => 'Applicant', 'last_name' => 'Example', 'email' => 'applicant@example.test', 'status' => 'pending', 'date_of_birth' => '1990-01-01', 'complete_address' => 'Private street', 'city' => 'CDO', 'province' => 'Misamis Oriental', 'mobile_number' => '09123456789', 'reason_for_joining' => 'Service', 'declaration' => true, 'consent' => true, 'consented_at' => now()]);
        $this->assertFalse(app(ApplicationNotificationService::class)->send($record));
        $this->assertDatabaseHas('applications', ['id' => $record->id]);
        $this->assertNotNull($record->fresh()->notification_email_failed_at);
        $this->assertStringNotContainsString('secret-provider-detail', $record->fresh()->notification_email_error);
    }

    public function test_revoked_refresh_token_fails_without_sending_or_exposing_secrets(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'secret-refresh'], 400)]);
        try {
            Mail::raw('Test email', fn ($m) => $m->to('admin@example.test')->subject('Test'));
            $this->fail('Revoked authorization must fail.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('Reauthorize', $e->getMessage());
            $this->assertStringNotContainsString('secret-refresh', $e->getMessage());
        }
        Http::assertSentCount(1);
    }
}
