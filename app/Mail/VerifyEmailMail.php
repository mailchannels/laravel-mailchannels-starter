<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerifyEmailMail extends TransactionalMail
{
    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your email address');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify-email', text: 'mail.verify-email-text');
    }
}
