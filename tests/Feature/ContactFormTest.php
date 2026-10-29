<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    public function test_contact_message_is_sent_to_contact_address_with_reply_to_submitter(): void
    {
        config(['mail.contact_to' => 'support@example.com']);

        $this->post('/contact', ['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'message' => 'Hello there'])
            ->assertRedirect('/contact')->assertSessionHas('status');

        $payloads = $this->sentPayloads();
        $this->assertCount(1, $payloads);
        $p = $payloads[0];
        $this->assertSame('support@example.com', $p['personalizations'][0]['to'][0]['email']);
        $this->assertSame('ada@example.test', $p['reply_to']['email']);
        $this->assertSame(config('mail.from.address'), $p['from']['email']);
        $this->assertSame('Contact form: Ada Lovelace', $p['subject']);
        $this->assertStringContainsString('Hello there', collect($p['content'])->firstWhere('type', 'text/plain')['value']);
    }

    public function test_contact_form_validates_input(): void
    {
        $this->post('/contact', ['name' => '', 'email' => 'not-an-email', 'message' => ''])
            ->assertSessionHasErrors(['name', 'email', 'message']);
        $this->post('/contact', ['name' => "Eve\r\nBcc: x@example.test", 'email' => 'eve@example.test', 'message' => 'hi'])
            ->assertSessionHasErrors(['name']);
        $this->assertSame([], $this->sentPayloads());
    }

    public function test_contact_form_requires_csrf_token(): void
    {
        // Laravel skips CSRF checks in unit tests; re-enable it here.
        $this->app->instance('env', 'local');
        $this->post('/contact', ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'hi'])->assertStatus(419);
        $this->assertSame([], $this->sentPayloads());
        $this->assertTrue(class_exists(ValidateCsrfToken::class));
    }
}
