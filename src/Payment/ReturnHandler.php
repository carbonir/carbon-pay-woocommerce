<?php

namespace Carbon\Pay\Payment;

use Carbon\Pay\Order\OrderMeta;
use WC_Order;

/**
 * Handles GET {callback_url}?wc_order=ID&status=…&token=… from two callers:
 *  - the payer's browser, redirected back by the hosted CPG page → redirect to the page that shows the
 *    result card (StatusView). No WooCommerce notice: it only repeated the card;
 *  - carbon-service's `orders:webhook`, server to server → plain 2xx JSON, no session.
 */
final class ReturnHandler
{
    public const RESULT_ARG = 'carbon_result';
    public const STATUS_ARG = 'carbon_status';

    private const FAILED_NOTES = [
        ReturnOutcome::CANCELLED => 'پرداخت در کربن لغو شد یا ناموفق بود.',
        ReturnOutcome::UNVERIFIED => 'پرداخت توسط کربن تایید نشد.',
        ReturnOutcome::EXPIRED => 'مهلت پرداخت با کربن تمام شد.',
    ];

    /** @var ReturnResolver */
    private $resolver;

    public function __construct(ReturnResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param array $query  $_GET
     * @param bool  $isBrowser False for the server-to-server webhook.
     */
    public function handle(array $query, bool $isBrowser, int $now): ReturnResponse
    {
        $orderId = isset($query['wc_order']) ? absint($query['wc_order']) : 0;
        $status = isset($query['status']) ? sanitize_key((string) $query['status']) : '';
        $token = isset($query['token']) ? sanitize_text_field((string) $query['token']) : '';

        $order = $orderId > 0 ? wc_get_order($orderId) : null;
        $order = $order instanceof WC_Order ? $order : null;

        if ($order !== null && isset($query['key']) && !hash_equals($order->get_order_key(), (string) $query['key'])) {
            $order = null;
        }

        $outcome = $this->resolver->resolve($order, $status, $token, $now);

        if ($order !== null) {
            $this->apply($order, $outcome, $token, $now);
        }

        if (!$isBrowser) {
            return ReturnResponse::json($order === null ? 404 : 200, ['outcome' => $outcome]);
        }

        if ($order === null) {
            return ReturnResponse::redirect(wc_get_checkout_url(), [['اطلاعات بازگشت از کربن معتبر نیست.', 'error']]);
        }

        if ($order->is_paid()) {
            return ReturnResponse::redirect($this->returnUrl($order));
        }

        return ReturnResponse::redirect(
            add_query_arg([self::RESULT_ARG => $outcome, self::STATUS_ARG => $status], self::statusPageUrl($order))
        );
    }

    public function apply(WC_Order $order, string $outcome, string $token, int $now): void
    {
        if ($outcome === ReturnOutcome::PAID) {
            $order->update_meta_data(OrderMeta::TOKEN, $token);
            $order->update_meta_data(OrderMeta::VERIFIED_AT, $now);
            $order->update_meta_data(OrderMeta::STATUS, OrderMeta::STATUS_PAID);
            OrderMeta::forgetRedirect($order);
            $order->add_order_note(sprintf('پرداخت با کربن تایید شد. شناسه پرداخت: %s', $token));
            $order->payment_complete($token);

            return;
        }

        if (ReturnOutcome::marksFailed($outcome)) {
            // Never `cancelled`: a cancelled order no longer needs payment, so a late webhook or a
            // pay-again could never complete it (backend issue #9 keeps the Carbon order payable).
            OrderMeta::forgetRedirect($order);
            if (!$order->has_status('failed')) {
                $order->update_status('failed', self::FAILED_NOTES[$outcome]);
            } else {
                $order->save();
            }
        }
    }

    /**
     * View-order for a logged-in owner; order-received (keyed URL) for guests.
     */
    public static function statusPageUrl(WC_Order $order): string
    {
        $customerId = (int) $order->get_customer_id();
        if ($customerId > 0 && get_current_user_id() === $customerId) {
            return $order->get_view_order_url();
        }

        return $order->get_checkout_order_received_url();
    }

    private function returnUrl(WC_Order $order): string
    {
        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        $gateway = $gateways[$order->get_payment_method()] ?? null;

        return $gateway ? $gateway->get_return_url($order) : $order->get_checkout_order_received_url();
    }
}
