<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApplicationAcknowledgment extends Mailable
{
    public function __construct(public string $reference) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your membership application has been received — '.$this->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.application-acknowledgment');
    }
}
