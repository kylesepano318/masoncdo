<?php

namespace App\Jobs;

use App\Models\LodgeApplication;
use App\Models\SiteSetting;
use App\Services\ApplicationNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverApplicationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public int $applicationId, public string $kind) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(ApplicationNotificationService $service): void
    {
        $application = LodgeApplication::find($this->applicationId);
        if (! $application) {
            return;
        }
        if ($this->kind === 'admin' && $application->notification_email_sent_at) {
            return;
        }
        if ($this->kind === 'applicant' && $application->acknowledgment_email_sent_at) {
            return;
        }
        $settings = SiteSetting::where('key', 'notifications')->value('value') ?? [];
        if ($this->kind === 'admin' && ($settings['send_application_notification_email'] ?? true) === false) {
            return;
        }
        $sent = $this->kind === 'admin' ? $service->send($application) : $service->acknowledge($application);
        if (! $sent) {
            throw new \RuntimeException('Application email delivery failed; retry scheduled.');
        }
    }
}
