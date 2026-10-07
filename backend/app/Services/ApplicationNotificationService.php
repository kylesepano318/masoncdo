<?php

namespace App\Services;

use App\Jobs\DeliverApplicationEmail;
use App\Mail\ApplicationAcknowledgment;
use App\Mail\NewMembershipApplication;
use App\Models\LodgeApplication;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ApplicationNotificationService
{
    private function settings(): array
    {
        return SiteSetting::where('key', 'notifications')->value('value') ?? [];
    }

    public function deliver(LodgeApplication $application): void
    {
        $settings = $this->settings();
        if (config('queue.default') !== 'sync') {
            if (($settings['send_application_notification_email'] ?? true) !== false) {
                DeliverApplicationEmail::dispatch($application->id, 'admin');
            }
            if ($settings['send_applicant_confirmation_email'] ?? false) {
                DeliverApplicationEmail::dispatch($application->id, 'applicant');
            }

            return;
        }
        $this->send($application, false, $settings);
        $this->acknowledge($application, $settings);
    }

    public function recipient(?array $settings = null): ?string
    {
        return (($settings ?? $this->settings())['application_notification_email'] ?? null) ?: config('lodge.notification_email');
    }

    public function send(LodgeApplication $application, bool $force = false, ?array $settings = null): bool
    {
        $settings ??= $this->settings();
        if (! $force && ($settings['send_application_notification_email'] ?? true) === false) {
            return false;
        }
        try {
            $email = $this->recipient($settings);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Notification recipient is not configured.');
            }
            Mail::to($email)->send(new NewMembershipApplication($application));
            $application->update(['notification_email_sent_at' => now(), 'notification_email_failed_at' => null, 'notification_email_error' => null]);

            return true;
        } catch (\Throwable $e) {
            $message = 'Notification delivery failed. Check the recipient and server mail configuration.';
            $application->update(['notification_email_failed_at' => now(), 'notification_email_error' => $message]);
            Log::warning('Membership notification delivery failed', ['application_reference' => $application->reference_number, 'exception_type' => get_class($e)]);

            return false;
        }
    }

    public function acknowledge(LodgeApplication $application, ?array $settings = null): bool
    {
        if (! (($settings ?? $this->settings())['send_applicant_confirmation_email'] ?? false)) {
            return true;
        }
        try {
            Mail::to($application->email)->send(new ApplicationAcknowledgment($application->reference_number));
            $application->forceFill(['acknowledgment_email_sent_at' => now(), 'acknowledgment_email_failed_at' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $application->forceFill(['acknowledgment_email_failed_at' => now()])->save();
            Log::warning('Applicant acknowledgment delivery failed', ['application_reference' => $application->reference_number, 'exception_type' => get_class($e)]);

            return false;
        }
    }
}
