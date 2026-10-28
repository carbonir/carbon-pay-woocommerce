<?php

namespace Carbon\Pay\Api;

interface HttpClient
{
    /**
     * POSTs a JSON body.
     *
     * @throws ApiException With kind KIND_NETWORK when no HTTP response was received.
     */
    public function postJson(string $url, array $payload): Response;
}
