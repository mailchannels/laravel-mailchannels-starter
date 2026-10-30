<?php

namespace Tests\Support;

use GuzzleHttp\Psr7\Response;
use MailChannelsStub\MessageStore;
use MailChannelsStub\StubApi;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that routes the SDK's HTTP calls to the bundled stub API
 * in-process, so tests exercise the real SDK and transport without a network.
 */
class StubHttpClient implements ClientInterface
{
    public function __construct(public readonly MessageStore $store = new MessageStore) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        parse_str($request->getUri()->getQuery(), $query);
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $result = (new StubApi($this->store))->handle(
            $request->getMethod(),
            $request->getUri()->getPath(),
            array_map('strval', $query),
            $headers,
            (string) $request->getBody(),
        );

        return new Response($result['status'], ['Content-Type' => 'application/json'], json_encode($result['body']));
    }
}
