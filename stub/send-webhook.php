<?php

declare(strict_types=1);

/*
 * Send a synthetic MailChannels webhook, signed with the stub's TEST-ONLY key.
 *
 *   php stub/send-webhook.php [--url=URL] [--tamper] [--bad-signature] [--stale] [--print]
 *
 *   --url            target (default: $APP_URL/webhooks/mailchannels or http://127.0.0.1:3006/...)
 *   --tamper         change the body after signing (expect 401)
 *   --bad-signature  sign with a different, random key (expect 401)
 *   --stale          sign with a created timestamp 10 minutes ago (expect 401)
 *   --print          print the request instead of sending it
 *
 * Exits 0 when the response status is 2xx, 1 otherwise.
 */

require __DIR__.'/autoload.php';

use MailChannelsStub\WebhookSigner;

$options = getopt('', ['url:', 'tamper', 'bad-signature', 'stale', 'print']);
$url = $options['url'] ?? rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:3006', '/').'/webhooks/mailchannels';

$body = WebhookSigner::sampleBody();
$created = isset($options['stale']) ? time() - 600 : time();
$secretKey = isset($options['bad-signature'])
    ? sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())
    : null;
$headers = WebhookSigner::headers($body, $created, $secretKey);

if (isset($options['tamper'])) {
    $body = str_replace('"delivered"', '"hard-bounced"', $body);
}

$headerLines = ['Content-Type: application/json'];
foreach ($headers as $name => $value) {
    $headerLines[] = "{$name}: {$value}";
}

echo "POST {$url}\n".implode("\n", $headerLines)."\n\n{$body}\n\n";
if (isset($options['print'])) {
    exit(0);
}

$context = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => implode("\r\n", $headerLines),
    'content' => $body,
    'ignore_errors' => true,
    'timeout' => 10,
]]);
$response = @file_get_contents($url, false, $context);
if ($response === false) {
    fwrite(STDERR, "Request to {$url} failed\n");
    exit(1);
}

$responseHeaders = function_exists('http_get_last_response_headers')
    ? (http_get_last_response_headers() ?? [])
    : ($http_response_header ?? []);
$statusLine = $responseHeaders[0] ?? 'HTTP/1.1 000';
echo "{$statusLine}\n{$response}\n";
preg_match('/\s(\d{3})\s?/', $statusLine, $m);
exit(isset($m[1]) && $m[1][0] === '2' ? 0 : 1);
