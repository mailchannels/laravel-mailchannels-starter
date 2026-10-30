<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessageMail extends TransactionalMail
{
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Sent from your verified domain; replies go to the submitter.
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: 'Contact form: '.$this->senderName,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contact', text: 'mail.contact-text');
    }
}
