<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;

/**
 * Base class for the app's emails.
 *
 * The x-mailchannels-* control headers are read (and stripped) by the
 * MailChannels transport:
 * - send-async=false posts to /send, so delivery errors surface immediately
 *   (the transport's default is the queued /send-async endpoint);
 * - transactional=true marks account mail as transactional.
 */
abstract class TransactionalMail extends Mailable
{
    public function headers(): Headers
    {
        return new Headers(text: [
            'x-mailchannels-send-async' => 'false',
            'x-mailchannels-transactional' => 'true',
        ]);
    }
}
