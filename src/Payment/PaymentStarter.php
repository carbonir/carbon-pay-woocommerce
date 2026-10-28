<?php

namespace Carbon\Pay\Payment;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Order\OrderMeta;
use WC_Order;

/**
 * Creates a Carbon payment (token + hosted-page URL) for an order, or reuses a fresh one
 * so that reloading the handoff page does not open a second Carbon order.
 */
final class PaymentStarter
{
    /** Leave this much of the token lifetime unused before reusing it. */
    private const REUSE_MARGIN = 120;

    /** @var CarbonApi */
    private $api;

    /** @var PaymentRequestBuilder */
    private $builder;

    /** @var int */
    private $tokenTtl;

    public function __construct(CarbonApi $api, PaymentRequestBuilder $builder, int $tokenTtl)
    {
        $this->api = $api;
        $this->builder = $builder;
        $this->tokenTtl = $tokenTtl;
    }

    /**
     * @return string The Carbon hosted-page URL to send the payer to.
     * @throws ApiException
     */
    public function start(WC_Order $order, string $callbackUrl, string $mobile, string $nationalCode, int $now): string
    {
        $reusable = $this->reusableRedirect($order, $now);
        if ($reusable !== null) {
            return $reusable;
        }

        $result = $this->api->createPayment($this->builder->build($order, $callbackUrl, $mobile, $nationalCode));

        OrderMeta::rememberToken($order, $result['token'], $result['redirect_url'], $now);
        $order->add_order_note(sprintf('خریدار به صفحه پرداخت کربن هدایت شد. شناسه پرداخت: %s', $result['token']));
        $order->save();

        return $result['redirect_url'];
    }

    public function reusableRedirect(WC_Order $order, int $now): ?string
    {
        $url = (string) $order->get_meta(OrderMeta::REDIRECT_URL);
        $createdAt = (int) $order->get_meta(OrderMeta::TOKEN_CREATED_AT);

        if ($url === '' || $createdAt <= 0) {
            return null;
        }

        return $now - $createdAt < $this->tokenTtl - self::REUSE_MARGIN ? $url : null;
    }
}
