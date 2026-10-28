<?php

namespace Carbon\Pay\Api;

/**
 * HttpClient backed by the WordPress HTTP API (honours proxies, CA bundle and timeouts).
 */
final class WpHttpClient implements HttpClient
{
    /** @var int */
    private $timeout;

    public function __construct(int $timeout = 20)
    {
        $this->timeout = $timeout;
    }

    public function postJson(string $url, array $payload): Response
    {
        $result = wp_remote_post($url, [
            'timeout' => $this->timeout,
            'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'user-agent' => 'CarbonPay-WooCommerce/' . CARBON_PAY_VERSION . '; ' . home_url('/'),
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($result)) {
            throw ApiException::network($result->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- logged, never printed.
        }

        return new Response(
            (int) wp_remote_retrieve_response_code($result),
            (string) wp_remote_retrieve_body($result)
        );
    }
}
