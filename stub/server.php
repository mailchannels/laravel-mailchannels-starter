<?php

declare(strict_types=1);

/*
 * Local stub of the MailChannels Email API (TEST-ONLY, never sends email).
 *
 *   php -S 127.0.0.1:4006 stub/server.php
 *
 * Then point the app at it: MAILCHANNELS_API_BASE_URL=http://127.0.0.1:4006/tx/v1
 * Received sends are kept in STUB_STORAGE (default: a file in the system temp dir).
 */

require __DIR__.'/autoload.php';

use MailChannelsStub\MessageStore;
use MailChannelsStub\StubApi;

$storage = getenv('STUB_STORAGE') ?: sys_get_temp_dir().'/mailchannels-stub-messages.json';

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
    }
}

$result = (new StubApi(new MessageStore($storage)))->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
    array_map('strval', $_GET),
    $headers,
    (string) file_get_contents('php://input'),
);

http_response_code($result['status']);
header('Content-Type: application/json');
echo json_encode($result['body'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";

error_log(sprintf('[stub] %s %s -> %d', $_SERVER['REQUEST_METHOD'] ?? '', $_SERVER['REQUEST_URI'] ?? '', $result['status']));
