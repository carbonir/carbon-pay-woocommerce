<?php

namespace Carbon\Pay\Api;

final class Response
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $body;

    public function __construct(int $statusCode, string $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Decoded JSON body, or null when the body is not a JSON object.
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true, 512, JSON_BIGINT_AS_STRING);

        return is_array($decoded) ? $decoded : null;
    }
}
