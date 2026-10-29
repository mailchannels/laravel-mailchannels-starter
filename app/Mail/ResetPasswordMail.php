<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends TransactionalMail
{
    public function __construct(public string $url, public int $expiresInMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your password');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reset-password', text: 'mail.reset-password-text');
    }
}
