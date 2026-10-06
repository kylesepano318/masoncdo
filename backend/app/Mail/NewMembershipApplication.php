<?php

namespace App\Mail;

use App\Models\LodgeApplication;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewMembershipApplication extends Mailable
{
    public function __construct(public LodgeApplication $application) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Membership Application — '.$this->application->reference_number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-application', with: ['reference' => $this->application->reference_number, 'applicant' => $this->application->first_name.' '.$this->application->last_name, 'submitted' => $this->application->submitted_at, 'reviewUrl' => rtrim(config('lodge.frontend_url'), '/').'/admin/applications/'.$this->application->id]);
    }
}
