<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;
use MailChannels\Client;
use MailChannels\Plugins\Symfony\Transport\MailChannelsApiTransport;
use Tests\Support\StubHttpClient;

abstract class TestCase extends BaseTestCase
{
    protected StubHttpClient $stub;

    protected function setUp(): void
    {
        parent::setUp();

        // Real SDK transport and client, with HTTP routed to the in-process stub API.
        $this->stub = new StubHttpClient;
        config([
            'services.mailchannels.api_key' => 'test-key',
            'services.mailchannels.base_url' => 'http://stub.test/tx/v1',
            'mail.default' => 'mailchannels-stub',
            'mail.mailers.mailchannels-stub' => ['transport' => 'mailchannels-stub'],
        ]);
        Mail::extend('mailchannels-stub', fn () => new MailChannelsApiTransport(
            apiKey: 'test-key',
            baseUrl: 'http://stub.test/tx/v1',
            httpClient: $this->stub,
        ));
        $this->app->instance(Client::class, new Client(
            apiKey: 'test-key',
            baseUrl: 'http://stub.test/tx/v1',
            httpClient: $this->stub,
        ));
    }

    /** @return list<array<string, mixed>> send payloads received by the stub */
    protected function sentPayloads(): array
    {
        return array_map(fn (array $m) => $m['payload'], $this->stub->store->all());
    }

    /** Extract the first URL with the given path from a payload's text body. */
    protected function linkFrom(array $payload, string $path): string
    {
        $text = collect($payload['content'])->firstWhere('type', 'text/plain')['value'];
        preg_match('#https?://\S+'.preg_quote($path, '#').'\?\S+#', $text, $m);
        $this->assertNotEmpty($m, "No {$path} link in email body");

        return $m[0];
    }
}
