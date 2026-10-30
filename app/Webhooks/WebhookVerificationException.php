<?php

namespace App\Webhooks;

use RuntimeException;

class WebhookVerificationException extends RuntimeException
{
    /** True when the failure is ours (e.g. key fetch failed), so MailChannels should retry. */
    public function __construct(string $message, public readonly bool $temporary = false)
    {
        parent::__construct($message);
    }
}
