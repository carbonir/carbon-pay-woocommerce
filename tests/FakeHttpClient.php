<?php

namespace Carbon\Pay\Tests;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\HttpClient;
use Carbon\Pay\Api\Response;

/**
 * Replays queued responses and records requests.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var array<int, Response|ApiException> */
    private $queue = [];

    /** @var array<int, array{url: string, payload: array}> */
    public $requests = [];

    public function respond(int $status, array $body): self
    {
        $this->queue[] = new Response($status, (string) json_encode($body, JSON_UNESCAPED_UNICODE));

        return $this;
    }

    public function respondRaw(int $status, string $body): self
    {
        $this->queue[] = new Response($status, $body);

        return $this;
    }

    public function failNetwork(string $reason = 'cURL error 28: Operation timed out'): self
    {
        $this->queue[] = ApiException::network($reason);

        return $this;
    }

    public function postJson(string $url, array $payload): Response
    {
        $this->requests[] = ['url' => $url, 'payload' => $payload];

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('FakeHttpClient: no response queued for ' . $url);
        }
        if ($next instanceof ApiException) {
            throw $next;
        }

        return $next;
    }
}
