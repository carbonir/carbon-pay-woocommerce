<?php

namespace Carbon\Pay\Api;

/**
 * Merchant side of the Carbon CPG API (carbon-service, prefix v1/cpg).
 * Every amount is an integer in Rial.
 */
final class CarbonApi
{
    /** @var HttpClient */
    private $http;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $merchantCode;

    public function __construct(HttpClient $http, string $baseUrl, string $merchantCode)
    {
        $this->http = $http;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->merchantCode = $merchantCode;
    }

    /**
     * POST /v1/cpg/payments
     *
     * @param array $payload Everything CreateOnlineValidator accepts except merchant_code.
     * @return array{token: string, redirect_url: string}
     */
    public function createPayment(array $payload): array
    {
        $data = $this->call('/v1/cpg/payments', $payload);
        $this->requireKeys($data, ['token', 'redirect_url']);

        return [
            'token' => (string) $data['token'],
            'redirect_url' => (string) $data['redirect_url'],
        ];
    }

    /**
     * POST /v1/cpg/payments/verify. Verifies a VERIFY_PENDING order; always reports the current state.
     *
     * @return array{token: string, invoice_amount: int, is_paid: bool}
     */
    public function verify(string $token, string $invoiceReference, int $invoiceAmount): array
    {
        $data = $this->call('/v1/cpg/payments/verify', [
            'token' => $token,
            'invoice_reference' => $invoiceReference,
            'invoice_amount' => $invoiceAmount,
        ]);
        $this->requireKeys($data, ['is_paid']);

        return [
            'token' => (string) ($data['token'] ?? $token),
            'invoice_amount' => (int) ($data['invoice_amount'] ?? $invoiceAmount),
            'is_paid' => $data['is_paid'] === true,
        ];
    }


    private function call(string $path, array $payload): array
    {
        $response = $this->http->postJson(
            $this->baseUrl . $path,
            ['merchant_code' => $this->merchantCode] + $payload
        );

        if (!$response->isSuccessful()) {
            throw ApiException::fromResponse($response); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged, never printed.
        }

        $json = $response->json();
        if (!isset($json['data']) || !is_array($json['data'])) {
            throw ApiException::malformed($response); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged, never printed.
        }

        return $json['data'];
    }

    private function requireKeys(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new ApiException(ApiException::KIND_MALFORMED, sprintf('Carbon response is missing "%s".', $key)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged, never printed.
            }
        }
    }
}
