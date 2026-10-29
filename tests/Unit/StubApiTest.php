<?php

namespace Tests\Unit;

use MailChannelsStub\MessageStore;
use MailChannelsStub\StubApi;
use PHPUnit\Framework\TestCase;

class StubApiTest extends TestCase
{
    public function test_send_requires_api_key_and_valid_shape(): void
    {
        $api = new StubApi($store = new MessageStore);
        $valid = json_encode([
            'from' => ['email' => 'a@example.com'], 'subject' => 's',
            'personalizations' => [['to' => [['email' => 'b@example.test']]]],
            'content' => [['type' => 'text/plain', 'value' => 'hi']],
        ]);

        $this->assertSame(401, $api->handle('POST', '/tx/v1/send', [], [], $valid)['status']);
        $this->assertSame(400, $api->handle('POST', '/tx/v1/send', [], ['X-Api-Key' => 'k'], '{}')['status']);
        $ok = $api->handle('POST', '/tx/v1/send', [], ['X-Api-Key' => 'k'], $valid);
        $this->assertSame(202, $ok['status']);
        $this->assertSame('sent', $ok['body']['results'][0]['status']);
        $this->assertCount(1, $api->handle('GET', '/_stub/messages', [], [], '')['body']);
        $api->handle('DELETE', '/_stub/messages', [], [], '');
        $this->assertSame([], $store->all());
    }

    public function test_public_key_endpoint(): void
    {
        $api = new StubApi(new MessageStore);
        $res = $api->handle('GET', '/tx/v1/webhook/public-key', ['id' => 'mckey'], [], '');
        $this->assertSame(200, $res['status']);
        $this->assertStringStartsWith('MCowBQYDK2VwAyEA', $res['body']['key']);
        $this->assertSame(404, $api->handle('GET', '/tx/v1/webhook/public-key', ['id' => 'nope'], [], '')['status']);
    }
}
