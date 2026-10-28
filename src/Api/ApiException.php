<?php

namespace Carbon\Pay\Api;

use RuntimeException;

/**
 * A failed Carbon CPG call, classified by the `type` field of carbon-service's error envelope:
 *   { "type": "<error_type>", "message": "..." }
 *   { "type": "validation_error", "errors": { "<field>": ["..."] } }
 */
final class ApiException extends RuntimeException
{
    public const KIND_NETWORK = 'network';
    public const KIND_VALIDATION = 'validation';
    public const KIND_MERCHANT = 'merchant';
    public const KIND_IP = 'ip';
    public const KIND_GATEWAY_INACTIVE = 'gateway_inactive';
    public const KIND_NOT_ALLOWED = 'not_allowed';
    public const KIND_INVALID_STATUS = 'invalid_status';
    public const KIND_TOKEN = 'token';
    public const KIND_AMOUNT_MISMATCH = 'amount_mismatch';
    public const KIND_RATE_LIMITED = 'rate_limited';
    public const KIND_SERVER = 'server';
    public const KIND_MALFORMED = 'malformed';

    private const TYPE_TO_KIND = [
        'validation_error' => self::KIND_VALIDATION,
        'merchant_code_error' => self::KIND_MERCHANT,
        'ip_address_error' => self::KIND_IP,
        'gateway_not_activated' => self::KIND_GATEWAY_INACTIVE,
        'authorization_error' => self::KIND_NOT_ALLOWED,
        'invalid_order_status' => self::KIND_INVALID_STATUS,
        'payment_token_error' => self::KIND_TOKEN,
        'not_found' => self::KIND_TOKEN,
        'invoice_amount_error' => self::KIND_AMOUNT_MISMATCH,
        'too_many_requests' => self::KIND_RATE_LIMITED,
        'service_unavailable' => self::KIND_SERVER,
        'server_error' => self::KIND_SERVER,
    ];

    /** @var string */
    private $kind;

    /** @var string|null */
    private $type;

    /** @var int */
    private $httpStatus;

    /** @var array<string, string[]> */
    private $fieldErrors;

    /**
     * @param array<string, string[]> $fieldErrors
     */
    public function __construct(string $kind, string $message, ?string $type = null, int $httpStatus = 0, array $fieldErrors = [])
    {
        parent::__construct($message);
        $this->kind = $kind;
        $this->type = $type;
        $this->httpStatus = $httpStatus;
        $this->fieldErrors = $fieldErrors;
    }

    public static function network(string $reason): self
    {
        return new self(self::KIND_NETWORK, $reason);
    }

    public static function malformed(Response $response): self
    {
        return new self(self::KIND_MALFORMED, 'Unexpected response body from Carbon.', null, $response->statusCode());
    }

    public static function fromResponse(Response $response): self
    {
        $json = $response->json() ?? [];
        $type = isset($json['type']) && is_string($json['type']) ? $json['type'] : null;

        $kind = $type !== null ? (self::TYPE_TO_KIND[$type] ?? null) : null;
        if ($kind === null) {
            $kind = $response->statusCode() === 403 ? self::KIND_NOT_ALLOWED
                : ($response->statusCode() >= 500 || $response->statusCode() === 0 ? self::KIND_SERVER : self::KIND_MALFORMED);
        }

        $fieldErrors = [];
        if (isset($json['errors']) && is_array($json['errors'])) {
            foreach ($json['errors'] as $field => $messages) {
                $fieldErrors[(string) $field] = array_values(array_filter(array_map('strval', (array) $messages)));
            }
        }

        $message = isset($json['message']) && is_string($json['message']) ? $json['message'] : '';
        if ($message === '' && $fieldErrors) {
            $message = implode(' - ', array_merge(...array_values($fieldErrors)));
        }
        if ($message === '') {
            $message = sprintf('Carbon API error (HTTP %d).', $response->statusCode());
        }

        return new self($kind, $message, $type, $response->statusCode(), $fieldErrors);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, string[]>
     */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function firstFieldError(string $field): ?string
    {
        return $this->fieldErrors[$field][0] ?? null;
    }

    /**
     * Errors the merchant must fix on their side; never shown verbatim to a payer.
     */
    public function isMerchantConfiguration(): bool
    {
        return in_array($this->kind, [self::KIND_MERCHANT, self::KIND_IP, self::KIND_GATEWAY_INACTIVE, self::KIND_NOT_ALLOWED], true);
    }
}
