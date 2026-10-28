<?php
/**
 * Runs inside a real WordPress + WooCommerce via `wp eval-file`. Prints PASS/FAIL lines and
 * exits non-zero on the first failure. Driven by tests/integration/run.sh.
 *
 * Arguments (after the file name): <phase>, where phase is "setup" or "after-callback".
 */

use Carbon\Pay\Admin\OrderScreen;
use Carbon\Pay\Frontend\StatusView;
use Carbon\Pay\GatewayId;
use Carbon\Pay\Migration\LegacyMigration;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Payment\ReturnOutcome;
use Carbon\Pay\Settings;

$phase = $args[0] ?? 'setup';

function cpw_check(bool $ok, string $label, $detail = null): void
{
    WP_CLI::line(($ok ? 'PASS ' : 'FAIL ') . $label . ($ok || $detail === null ? '' : ' :: ' . print_r($detail, true)));
    if (!$ok) {
        exit(1);
    }
}

function cpw_queue(array $responses): void
{
    update_option('cpw_responses', $responses, false);
    update_option('cpw_requests', [], false);
}

function cpw_capture(callable $fn): string
{
    ob_start();
    $fn();

    return (string) ob_get_clean();
}

function cpw_gateway(): Carbon\Pay\Gateway\Gateway
{
    WC()->payment_gateways()->init();

    return WC()->payment_gateways()->payment_gateways()[GatewayId::ID];
}

if ($phase === 'setup') {
    WP_CLI::line('WooCommerce ' . WC()->version . ' / WordPress ' . get_bloginfo('version') . ' / PHP ' . PHP_VERSION);

    // --- Migration from GSMPay -------------------------------------------------------------
    $settings = get_option(Settings::OPTION);
    cpw_check(is_array($settings) && $settings['merchant_code'] === 'MRC-LEGACY' && $settings['enabled'] === 'yes', 'legacy settings migrated', $settings);
    cpw_check(!array_intersect_key($settings, array_flip(Settings::OBSOLETE_KEYS)), 'no title / description / environment saved', $settings);

    $product = new WC_Product_Simple();
    $product->set_name('گوشی سامسونگ Galaxy A55');
    $product->set_regular_price('18500000');
    $product->save();

    $legacy = wc_create_order();
    $legacy->add_product($product, 1);
    $legacy->set_payment_method(GatewayId::LEGACY_ID);
    $legacy->update_meta_data('gsmpay_token', 'gsm-legacy-token');
    $legacy->calculate_totals();
    $legacy->set_status('processing');
    $legacy->save();

    (new LegacyMigration())->runOrderBatch();
    $legacy = wc_get_order($legacy->get_id());
    cpw_check($legacy->get_payment_method() === GatewayId::ID, 'legacy order moved to WC_Carbon_Pay', $legacy->get_payment_method());
    cpw_check($legacy->get_meta(OrderMeta::TOKEN) === 'gsm-legacy-token', 'legacy token copied');

    // --- Gateway registration ---------------------------------------------------------------
    update_option('woocommerce_currency', 'IRT');
    $settings['merchant_code'] = 'MRC-104582';
    update_option(Settings::OPTION, $settings);
    $gateway = cpw_gateway();
    cpw_check($gateway instanceof WC_Payment_Gateway, 'gateway registered');
    cpw_check($gateway->is_available(), 'gateway available with merchant code + IRT');
    cpw_check($gateway->supports('products') && !$gateway->supports('refunds') && !$gateway->supports('subscriptions'), 'supports = products only');

    $registry = Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class);
    $registry->initialize(); // Normally done on checkout requests.
    cpw_check($registry->is_registered(GatewayId::ID), 'block payment method registered');

    $fields = Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);
    $additional = $fields->get_additional_fields();
    cpw_check(isset($additional['carbon/mobile'], $additional['carbon/national_code']), 'block checkout fields registered', array_keys($additional));
    cpw_check($additional['carbon/mobile']['required'] === false, 'block fields optional for other methods');

    // --- Rendering ---------------------------------------------------------------------------
    $html = cpw_capture([$gateway, 'payment_fields']);
    cpw_check(strpos($html, 'carbon-pay cp-checkout') !== false && strpos($html, 'class="cp-chip"') !== false && strpos($html, 'name="carbon_mobile"') !== false, 'classic fieldset renders chips + payer fields');

    $html = cpw_capture([$gateway, 'admin_options']);
    cpw_check(strpos($html, 'cp-header') !== false && strpos($html, 'wc_carbon_pay') !== false, 'settings header + callback URL render');
    cpw_check(strpos($html, 'woocommerce_WC_Carbon_Pay_environment') === false && strpos($html, 'woocommerce_WC_Carbon_Pay_title') === false && strpos($html, 'woocommerce_WC_Carbon_Pay_description') === false, 'no environment, title or description field');
    cpw_check($gateway->get_title() === Settings::TITLE, 'fixed gateway title');

    // --- process_payment: Carbon validation error ---------------------------------------------
    $order = wc_create_order();
    $order->add_product($product, 1);
    $order->set_billing_first_name('علی');
    $order->set_billing_last_name('رضایی');
    $order->set_payment_method(GatewayId::ID);
    $order->calculate_totals();
    $order->save();

    $_POST['carbon_mobile'] = '۰۹۱۲۱۲۳۴۵۶۷';
    $_POST['carbon_national_code'] = '0012345679';

    WC()->initialize_session();
    cpw_queue([[422, ['type' => 'validation_error', 'errors' => ['national_code' => ['متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد.']]]]]);
    $result = $gateway->process_payment($order->get_id());
    $notices = wc_get_notices('error');
    cpw_check($result['result'] === 'failure', 'shahkar error fails the payment');
    cpw_check(strpos(wp_json_encode($notices, JSON_UNESCAPED_UNICODE), 'مطابقت ندارد') !== false, 'shahkar notice shown', $notices);
    wc_clear_notices();

    // --- process_payment: success ------------------------------------------------------------
    cpw_queue([[200, ['data' => ['token' => 'tok-int-1', 'redirect_url' => 'https://cpg.carbon.ir/payments/tok-int-1']]]]);
    $result = $gateway->process_payment($order->get_id());
    $order = wc_get_order($order->get_id());
    $request = get_option('cpw_requests')[0];
    cpw_check($result['result'] === 'success' && strpos($result['redirect'], 'order-pay') !== false, 'payment created, redirect to order-pay', $result);
    cpw_check($request['url'] === 'https://api.carbon.ir/v1/cpg/payments', 'production API base');
    cpw_check($request['payload']['invoice_amount'] === 185000000 && $request['payload']['payer_mobile'] === '09121234567', 'payload in Rial with normalised mobile', $request['payload']);
    cpw_check($order->get_meta(OrderMeta::TOKEN) === 'tok-int-1' && $order->get_meta(OrderMeta::PAYER_NATIONAL_CODE) === '0012345679', 'token + payer meta stored (HPOS-safe)');

    // --- Block checkout path: no $_POST, fields saved as contact additional fields --------------
    $_POST = [];
    cpw_check($gateway->validate_fields() === true, 'block checkout: validate_fields defers to process_payment');

    $block = wc_create_order();
    $block->add_product($product, 1);
    $block->set_billing_first_name('سارا');
    $block->set_billing_last_name('محمدی');
    $block->set_payment_method(GatewayId::ID);
    $block->update_meta_data('_wc_other/carbon/mobile', '09351234567');
    $block->update_meta_data('_wc_other/carbon/national_code', '0012345679');
    $block->calculate_totals();
    $block->save();

    cpw_queue([[200, ['data' => ['token' => 'tok-block', 'redirect_url' => 'https://cpg.carbon.ir/payments/tok-block']]]]);
    $result = $gateway->process_payment($block->get_id());
    $request = get_option('cpw_requests')[0];
    cpw_check($result['result'] === 'success' && $request['payload']['payer_mobile'] === '09351234567', 'block checkout: payer read from additional fields', $result);

    $empty = wc_create_order();
    $empty->add_product($product, 1);
    $empty->set_billing_first_name('سارا');
    $empty->set_billing_last_name('محمدی');
    $empty->calculate_totals();
    $empty->save();
    cpw_queue([]);
    $result = $gateway->process_payment($empty->get_id());
    cpw_check($result['result'] === 'failure' && get_option('cpw_requests') === [], 'block checkout: missing mobile rejected before calling Carbon');
    wc_clear_notices();

    // Hand the callback test to run.sh.
    update_option('cpw_order_id', $order->get_id(), false);
    update_option('cpw_callback', add_query_arg(['status' => 'failed', 'token' => 'tok-int-1'], Carbon\Pay\Plugin::callbackUrl($order)), false);
    cpw_queue([[200, ['data' => ['token' => 'tok-int-1', 'invoice_amount' => 185000000, 'is_paid' => true]]]]);

    WP_CLI::line('callback ' . get_option('cpw_callback'));
    exit(0);
}

if ($phase === 'after-callback') {
    $order = wc_get_order((int) get_option('cpw_order_id'));
    cpw_check($order->is_paid(), 'webhook with status=failed but verify is_paid=true completes the order', $order->get_status());
    cpw_check($order->get_transaction_id() === 'tok-int-1', 'transaction id = Carbon token');

    $gateway = cpw_gateway();

    // --- Metabox / thank-you rendering --------------------------------------------------------
    $html = cpw_capture(static function () use ($order) {
        (new OrderScreen())->renderMetabox($order);
    });
    cpw_check(strpos($html, 'cp-metabox') !== false && strpos($html, 'پرداخت شد') !== false && strpos($html, '۱۸۵٬۰۰۰٬۰۰۰') !== false, 'metabox renders paid + Rial amount');

    $html = (new StatusView(1200))->render($order, ReturnOutcome::PAID);
    cpw_check(strpos($html, 'خرید شما با کربن ثبت شد') !== false, 'thank-you block renders');

    // --- No Carbon refunds: WooCommerce records the refund without calling Carbon --------
    cpw_check(!$gateway->supports('refunds'), 'gateway does not support refunds');
    cpw_queue([]);
    $refund = wc_create_refund(['order_id' => $order->get_id(), 'amount' => 1000, 'refund_payment' => true]);
    cpw_check(is_wp_error($refund), 'automatic refund through Carbon is refused', $refund);
    $refund = wc_create_refund(['order_id' => $order->get_id(), 'amount' => 1000, 'refund_payment' => false]);
    cpw_check(!is_wp_error($refund) && get_option('cpw_requests') === [], 'manual refund recorded, Carbon not called');

    $actions = apply_filters('woocommerce_order_actions', [], wc_get_order($order->get_id()));
    cpw_check(!isset($actions['carbon_full_reverse']), 'no full-reverse order action');

    exit(0);
}
