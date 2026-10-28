<?php

namespace Carbon\Pay\Payment;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Support\Money;
use WC_Order;

/**
 * Decides the outcome of a callback. The result comes from verify only: carbon-service reports
 * `status=failed` for bank-paid orders (backend issue #8), so `?status=` is never trusted on its own.
 */
final class ReturnResolver
{
    /** @var CarbonApi */
    private $api;

    /** @var int */
    private $tokenTtl;

    public function __construct(CarbonApi $api, int $tokenTtl)
    {
        $this->api = $api;
        $this->tokenTtl = $tokenTtl;
    }

    public function resolve(?WC_Order $order, string $status, string $token, int $now): string
    {
        if ($order === null) {
            return ReturnOutcome::UNKNOWN_ORDER;
        }

        if ($order->is_paid() || !$order->needs_payment()) {
            return ReturnOutcome::ALREADY_DONE;
        }

        if ($token === '' || !$this->isKnownToken($order, $token)) {
            return ReturnOutcome::INVALID_TOKEN;
        }

        try {
            $result = $this->api->verify(
                $token,
                (string) $order->get_order_number(),
                Money::toRial((string) $order->get_currency(), $order->get_total())
            );
        } catch (ApiException $e) {
            return $e->kind() === ApiException::KIND_TOKEN ? ReturnOutcome::INVALID_TOKEN : ReturnOutcome::API_ERROR;
        }

        if ($result['is_paid']) {
            return ReturnOutcome::PAID;
        }

        if ($status === 'success') {
            return ReturnOutcome::PENDING;
        }

        $createdAt = (int) $order->get_meta(OrderMeta::TOKEN_CREATED_AT);
        if ($createdAt > 0 && $now - $createdAt >= $this->tokenTtl) {
            return ReturnOutcome::EXPIRED;
        }

        return $status === 'failed' ? ReturnOutcome::CANCELLED : ReturnOutcome::UNVERIFIED;
    }

    private function isKnownToken(WC_Order $order, string $token): bool
    {
        foreach (OrderMeta::tokens($order) as $known) {
            if (hash_equals($known, $token)) {
                return true;
            }
        }

        return false;
    }
}
