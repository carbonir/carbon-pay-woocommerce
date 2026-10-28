<?php

namespace Carbon\Pay\Admin;

use Carbon\Pay\GatewayId;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Plugin;
use Carbon\Pay\Settings;
use Carbon\Pay\Support\Format;
use Carbon\Pay\Support\Money;
use Carbon\Pay\View\Template;
use WC_Order;
use WP_Post;

/**
 * Everything the plugin adds to the admin order edit screen (legacy posts and HPOS): the payer's
 * national code and mobile, and the «پرداخت کربن» metabox. Refunds are not handled by the plugin
 * WooCommerce can still record a manual refund.
 */
final class OrderScreen
{
    public function register(): void
    {
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'renderPayer']);
        add_action('add_meta_boxes', [$this, 'addMetabox'], 10, 2);
    }

    /**
     * @param WC_Order $order
     */
    public function renderPayer($order): void
    {
        if (!$order instanceof WC_Order) {
            return;
        }

        $nationalCode = (string) $order->get_meta(OrderMeta::PAYER_NATIONAL_CODE);
        if ($nationalCode !== '') {
            printf('<p><strong>%s</strong> <span dir="ltr">%s</span></p>', 'کد ملی خریدار:', esc_html($nationalCode));
        }

        $mobile = (string) $order->get_meta(OrderMeta::PAYER_MOBILE);
        if ($mobile !== '') {
            printf(
                '<p><strong>%s</strong> <a href="%s" dir="ltr">%s</a></p>',
                'موبایل خریدار:',
                esc_url('tel:' . $mobile),
                esc_html($mobile)
            );
        }
    }

    /**
     * @param string           $screenId
     * @param WP_Post|WC_Order $object
     */
    public function addMetabox($screenId, $object = null): void
    {
        $order = $object instanceof WC_Order ? $object : ($object instanceof WP_Post ? wc_get_order($object->ID) : null);
        if (!$order instanceof WC_Order || !GatewayId::owns($order)) {
            return;
        }

        add_meta_box('carbon-payment', 'پرداخت کربن', [$this, 'renderMetabox'], $screenId, 'side', 'high');
    }

    /**
     * @param WP_Post|WC_Order $object
     */
    public function renderMetabox($object): void
    {
        $order = $object instanceof WC_Order ? $object : wc_get_order($object->ID);
        if (!$order instanceof WC_Order) {
            return;
        }

        Plugin::instance()->assets()->enqueueAdmin();

        $verifiedAt = (int) $order->get_meta(OrderMeta::VERIFIED_AT);
        $token = OrderMeta::token($order);

        $html = Template::render('admin/order-metabox', [
            'status' => self::paymentStatus($order),
            'amount_rial' => Format::number(Money::toRial((string) $order->get_currency(), $order->get_total())),
            'verified_at' => $verifiedAt > 0 ? Format::persianDigits(wp_date('j F Y، H:i', $verifiedAt)) : '',
            'token' => $token,
            'token_short' => Format::shortToken($token),
            'missing_merchant' => Settings::load()->merchantCode() === '',
            'settings_url' => SettingsPage::url(),
        ]);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
    }

    public static function paymentStatus(WC_Order $order): string
    {
        if ($order->get_meta(OrderMeta::STATUS) === OrderMeta::STATUS_REFUND || $order->has_status('refunded')) {
            return 'refund';
        }

        return $order->is_paid() || $order->get_date_paid() ? 'paid' : 'pending';
    }
}
