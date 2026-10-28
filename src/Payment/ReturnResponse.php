<?php

namespace Carbon\Pay\Payment;

/**
 * What the callback endpoint should send. Kept as data so ReturnHandler stays testable;
 * Plugin::sendReturnResponse() performs the redirect / JSON output.
 */
final class ReturnResponse
{
    /** @var string redirect|json */
    public $type;

    /** @var string */
    public $url = '';

    /** @var int */
    public $status = 302;

    /** @var array */
    public $body = [];

    /** @var array<int, array{0: string, 1: string}> [message, notice type] */
    public $notices = [];

    public static function redirect(string $url, array $notices = []): self
    {
        $response = new self();
        $response->type = 'redirect';
        $response->url = $url;
        $response->notices = $notices;

        return $response;
    }

    public static function json(int $status, array $body): self
    {
        $response = new self();
        $response->type = 'json';
        $response->status = $status;
        $response->body = $body;

        return $response;
    }
}
