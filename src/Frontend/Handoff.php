<?php

namespace Carbon\Pay\Frontend;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\GatewayId;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Payment\ErrorPresenter;
use Carbon\Pay\Plugin;
use Carbon\Pay\Support\Format;
use Carbon\Pay\View\Template;
use WC_Order;

/**
 * The order-pay (receipt) step: makes sure a Carbon payment exists before any output, then renders
 * the handoff screen that forwards the payer to the hosted Carbon page.
 *
 * The payment is normally created in Gateway::process_payment(); this covers reloads after the
 * token went stale and orders that reach order-pay some other way.
 */
final class Handoff
{
    /** Seconds the hand-off card shows before forwarding the payer (design 1d). */
    public const DELAY = 3;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'prepare']);
    }

    public function prepare(): void
    {
        $order = $this->currentReceiptOrder();
        if ($order === null) {
            return;
        }

        $plugin = Plugin::instance();
        $starter = $plugin->paymentStarter();
        if ($starter !== null && $starter->reusableRedirect($order, time()) !== null) {
            return;
        }

        $error = ErrorPresenter::generic();
        if ($starter !== null) {
            try {
                $starter->start(
                    $order,
                    Plugin::callbackUrl($order),
                    (string) $order->get_meta(OrderMeta::PAYER_MOBILE),
                    (string) $order->get_meta(OrderMeta::PAYER_NATIONAL_CODE),
                    time()
                );

                return;
            } catch (ApiException $e) {
                Plugin::logApiError('create payment (order-pay)', $e, $order);
                $error = ErrorPresenter::forPayer($e);
            }
        }

        wc_add_notice($error->notice, 'error');
        wp_safe_redirect($order->get_checkout_payment_url());
        exit;
    }

    public function render(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }

        $redirect = (string) $order->get_meta(OrderMeta::REDIRECT_URL);
        if ($redirect === '') {
            return;
        }

        Plugin::instance()->assets()->enqueueFrontend();
        wp_enqueue_script('carbon-pay-handoff');

        $html = Template::render('checkout/handoff', [
            'redirect_url' => $redirect,
            'host' => (string) wp_parse_url($redirect, PHP_URL_HOST),
            'order_number' => Format::persianDigits((string) $order->get_order_number()),
            'delay' => self::DELAY,
        ]);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
    }

    /**
     * The order on /checkout/order-pay/{id}/?key=… (receipt view, not the pay_for_order form).
     */
    private function currentReceiptOrder(): ?WC_Order
    {
        if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-pay')) {
            return null;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the order key authorises the view.
        if (isset($_GET['pay_for_order'])) {
            return null;
        }

        $order = wc_get_order(absint(get_query_var('order-pay')));
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        // phpcs:enable

        if (!$order instanceof WC_Order || !hash_equals($order->get_order_key(), $key)) {
            return null;
        }

        if ($order->get_payment_method() !== GatewayId::ID || !$order->needs_payment()) {
            return null;
        }

        return $order;
    }
}
