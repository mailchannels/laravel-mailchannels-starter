<?php

declare(strict_types=1);

namespace MailChannelsStub;

/**
 * A tiny, dependency-free imitation of the MailChannels Email API for local
 * development and tests. It accepts sends, records them, and serves the
 * TEST-ONLY webhook public key. It never delivers email.
 *
 *   POST   /tx/v1/send                       record a send, 202 like the real API
 *   POST   /tx/v1/send-async                 record a queued send, 202
 *   GET    /tx/v1/webhook/public-key?id=...  the stub's TEST-ONLY public key
 *   GET    /_stub/messages                   JSON list of received send payloads
 *   DELETE /_stub/messages                   clear the list
 */
final class StubApi
{
    public function __construct(private readonly MessageStore $store) {}

    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers  header names in any case
     * @return array{status: int, body: array<mixed>|null}
     */
    public function handle(string $method, string $path, array $query, array $headers, string $body): array
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $path = '/'.trim($path, '/');

        return match (true) {
            $path === '/tx/v1/send' && $method === 'POST' => $this->send($headers, $body, async: false),
            $path === '/tx/v1/send-async' && $method === 'POST' => $this->send($headers, $body, async: true),
            $path === '/tx/v1/webhook/public-key' && $method === 'GET' => $this->publicKey($query),
            $path === '/_stub/messages' && $method === 'GET' => ['status' => 200, 'body' => $this->store->all()],
            $path === '/_stub/messages' && $method === 'DELETE' => $this->clear(),
            default => ['status' => 404, 'body' => ['errors' => ["No stub route for {$method} {$path}"]]],
        };
    }

    /** @param array<string, string> $headers */
    private function send(array $headers, string $body, bool $async): array
    {
        if (trim($headers['x-api-key'] ?? '') === '') {
            return ['status' => 401, 'body' => ['errors' => ['Missing X-Api-Key header']]];
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return ['status' => 400, 'body' => ['errors' => ['Request body must be a JSON object']]];
        }

        $errors = self::validate($payload);
        if ($errors !== []) {
            return ['status' => 400, 'body' => ['errors' => $errors]];
        }

        $requestId = 'stub-'.bin2hex(random_bytes(8));
        $results = [];
        foreach (array_values($payload['personalizations']) as $index => $_) {
            $results[] = [
                'index' => $index,
                'message_id' => 'stub-'.bin2hex(random_bytes(8)).'@example.test',
                'status' => 'sent',
            ];
        }

        $this->store->add([
            'endpoint' => $async ? 'send-async' : 'send',
            'received_at' => gmdate('c'),
            'request_id' => $requestId,
            'payload' => $payload,
        ]);

        return $async
            ? ['status' => 202, 'body' => ['request_id' => $requestId, 'queued_at' => gmdate('c')]]
            : ['status' => 202, 'body' => ['request_id' => $requestId, 'results' => $results]];
    }

    /**
     * Basic shape checks, mirroring the real API's required fields.
     *
     * @param  array<mixed>  $payload
     * @return list<string>
     */
    public static function validate(array $payload): array
    {
        $errors = [];
        $isEmail = static fn ($v): bool => is_array($v) && is_string($v['email'] ?? null)
            && filter_var($v['email'], FILTER_VALIDATE_EMAIL) !== false;

        if (! $isEmail($payload['from'] ?? null)) {
            $errors[] = 'from.email is required and must be an email address';
        }
        if (! is_string($payload['subject'] ?? null) || $payload['subject'] === '') {
            $errors[] = 'subject is required';
        }
        $personalizations = $payload['personalizations'] ?? null;
        if (! is_array($personalizations) || $personalizations === []) {
            $errors[] = 'personalizations must be a non-empty array';
        } else {
            foreach ($personalizations as $i => $p) {
                $to = is_array($p) ? ($p['to'] ?? null) : null;
                if (! is_array($to) || $to === [] || array_filter($to, fn ($r) => ! $isEmail($r)) !== []) {
                    $errors[] = "personalizations[{$i}].to must be a non-empty array of {email}";
                }
            }
        }
        $content = $payload['content'] ?? null;
        if (! is_array($content) || $content === []) {
            $errors[] = 'content must be a non-empty array';
        } else {
            foreach ($content as $i => $c) {
                if (! is_array($c) || ! is_string($c['type'] ?? null) || ! is_string($c['value'] ?? null)) {
                    $errors[] = "content[{$i}] needs string type and value";
                }
            }
        }
        if (isset($payload['reply_to']) && ! $isEmail($payload['reply_to'])) {
            $errors[] = 'reply_to.email must be an email address';
        }

        return $errors;
    }

    /** @param array<string, string> $query */
    private function publicKey(array $query): array
    {
        $id = $query['id'] ?? '';
        if ($id !== TestKeys::KEY_ID) {
            return ['status' => 404, 'body' => ['errors' => ["Unknown key id '{$id}'"]]];
        }

        return ['status' => 200, 'body' => ['id' => TestKeys::KEY_ID, 'key' => TestKeys::publicKeySpkiBase64()]];
    }

    private function clear(): array
    {
        $this->store->clear();

        return ['status' => 200, 'body' => ['deleted' => true]];
    }
}
