<?php

namespace App\Services;

use App\Mail\ApplicationAcknowledgment;
use App\Mail\NewMembershipApplication;
use App\Models\LodgeApplication;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ApplicationNotificationService
{
    public function recipient(): ?string
    {
        return (SiteSetting::where('key', 'notifications')->first()?->value['application_notification_email'] ?? null) ?: config('lodge.notification_email');
    }

    public function send(LodgeApplication $application, bool $force = false): bool
    {
        if (! $force && (SiteSetting::where('key', 'notifications')->first()?->value['send_application_notification_email'] ?? true) === false) {
            return false;
        }
        try {
            $email = $this->recipient();
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

    public function acknowledge(LodgeApplication $application): void
    {
        if (! (SiteSetting::where('key', 'notifications')->first()?->value['send_applicant_confirmation_email'] ?? false)) {
            return;
        }
        try {
            Mail::to($application->email)->send(new ApplicationAcknowledgment($application->reference_number));
        } catch (\Throwable $e) {
            Log::warning('Applicant acknowledgment delivery failed', ['application_reference' => $application->reference_number, 'exception_type' => get_class($e)]);
        }
    }
}
