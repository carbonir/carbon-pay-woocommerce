<?php

namespace Carbon\Pay\Gateway;

use Carbon\Pay\Admin\ConnectionCheck;
use Carbon\Pay\Admin\SettingsPage;
use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Checkout\PayerDetails;
use Carbon\Pay\GatewayId;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Payment\ErrorPresenter;
use Carbon\Pay\Payment\PayerError;
use Carbon\Pay\Plugin;
use Carbon\Pay\Settings;
use Carbon\Pay\Support\Money;
use Carbon\Pay\View\Template;
use WC_Order;
use WC_Payment_Gateway;

/**
 * The «کربن — پرداخت با قدرت خرید» payment method.
 */
class Gateway extends WC_Payment_Gateway
{
    public const ID = GatewayId::ID;

    public const ORDER_BUTTON_TEXT = 'پرداخت با کربن';

    public const SESSION_ERRORS = 'carbon_pay_errors';
    public const SESSION_PAYER = 'carbon_pay_payer';

    public function __construct()
    {
        $this->id = self::ID;
        $this->icon = (string) apply_filters('carbon_pay_icon', CARBON_PAY_URL . 'assets/logos/carbon-pay-icon.svg');
        $this->has_fields = true;
        $this->method_title = Settings::TITLE;
        $this->method_description = 'تنظیمات پرداخت با کربن';
        $this->order_button_text = self::ORDER_BUTTON_TEXT;
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        // Fixed Carbon copy; not a setting.
        $this->title = Settings::TITLE;
        $this->description = '';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
    }

    public function init_form_fields()
    {
        $this->form_fields = apply_filters('carbon_pay_form_fields', [
            'enabled' => [
                'title' => 'فعالسازی',
                'label' => 'فعالسازی پرداخت با کربن',
                'description' => 'برای فعالسازی درگاه باید چک باکس را تیک بزنید.',
                'type' => 'checkbox',
                'default' => 'yes',
                'desc_tip' => true,
            ],
            'merchant_code' => [
                'title' => 'کد پذیرنده',
                'description' => 'کد پذیرنده دریافتی از کربن',
                'type' => 'text',
                'default' => '',
                'desc_tip' => true,
                'class' => 'ltr',
                'custom_attributes' => ['dir' => 'ltr', 'aria-describedby' => 'carbon-pay-mc-warn'],
            ],
        ]);
    }

    /**
     * Hidden when it cannot work: no merchant code, or a store currency Carbon cannot settle.
     */
    public function is_available()
    {
        if (!parent::is_available()) {
            return false;
        }

        return trim((string) $this->get_option('merchant_code')) !== ''
            && Money::isSupported((string) get_woocommerce_currency());
    }

    /**
     * Classic checkout prints get_title() and get_icon() side by side in the payment option label.
     * The storefront gets the designed label (templates/checkout/label.php) and carbon-pay.css hides
     * the plain title next to it, so the title itself stays plain text for orders and emails.
     */
    public function get_icon()
    {
        if (is_admin() && !wp_doing_ajax()) {
            return parent::get_icon();
        }

        return apply_filters('woocommerce_gateway_icon', Template::render('checkout/label'), $this->id);
    }

    /**
     * Used by WooCommerce's Payments page for the «مدیریت» link (see SettingsPage::section()).
     */
    public function get_settings_url()
    {
        return SettingsPage::url();
    }

    public function admin_options()
    {
        echo Template::render('admin/settings-header'); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in template.
        parent::admin_options();
        $html = Template::render('admin/connection-info', [
            'callback_url' => Plugin::callbackBaseUrl(),
            'check_url' => admin_url('admin-ajax.php'),
            'check_nonce' => wp_create_nonce(ConnectionCheck::ACTION),
            'last_check' => ConnectionCheck::lastResult(Settings::load()->merchantCode()),
        ]);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
    }

    public function payment_fields()
    {
        $errors = [];
        $session = WC()->session;
        if ($session) {
            foreach ((array) $session->get(self::SESSION_ERRORS, []) as $data) {
                $error = is_array($data) ? PayerError::fromArray($data) : null;
                if ($error) {
                    $errors[] = $error;
                }
            }
            $session->set(self::SESSION_ERRORS, null);
        }

        $payer = $this->prefilledPayer();

        $html = Template::render('checkout/fieldset', [
            'gateway_id' => $this->id,
            'mobile' => $payer[0],
            'national_code' => $payer[1],
            'errors' => $errors,
        ]);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
    }

    /**
     * Classic checkout only. The Store API also calls this for block checkout, with $_POST set to the
     * block's payment data, which never contains our fields: those live in the contact additional
     * fields and are validated in process_payment() once they are saved on the order.
     */
    public function validate_fields()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce.
        if (!array_key_exists(PayerDetails::FIELD_MOBILE, $_POST)) {
            return true;
        }

        $payer = $this->payerFromRequest(null);

        if (WC()->session) {
            WC()->session->set(self::SESSION_PAYER, [$payer->mobile, $payer->nationalCode]);
        }

        $errors = $payer->validate();
        if ($errors) {
            $this->reportErrors($errors);

            return false;
        }

        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return $this->failure([ErrorPresenter::generic()]);
        }

        $payer = $this->payerFromRequest($order);
        $errors = $payer->validate();
        if ($errors) {
            return $this->failure($errors);
        }

        $order->update_meta_data(OrderMeta::PAYER_MOBILE, $payer->mobile);
        $order->update_meta_data(OrderMeta::PAYER_NATIONAL_CODE, $payer->nationalCode);
        OrderMeta::forgetRedirect($order);
        $order->save();

        $starter = Plugin::instance()->paymentStarter();
        if ($starter === null) {
            Plugin::log('Payment not started: merchant code is not configured.', ['order' => $order->get_id()]);

            return $this->failure([ErrorPresenter::generic()]);
        }

        try {
            $starter->start($order, Plugin::callbackUrl($order), $payer->mobile, $payer->nationalCode, time());
        } catch (ApiException $e) {
            Plugin::logApiError('create payment', $e, $order);

            return $this->failure([ErrorPresenter::forPayer($e)]);
        }

        if (WC()->session) {
            WC()->session->set(self::SESSION_ERRORS, null);
        }

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }


    public function receipt_page($order_id)
    {
        Plugin::instance()->handoff()->render((int) $order_id);
    }

    /**
     * Mobile / national code from, in order: the classic form, the block checkout fields saved on the
     * order, the order meta of an earlier attempt, the customer's saved contact fields.
     */
    private function payerFromRequest(?WC_Order $order): PayerDetails
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce.
        $post = wp_unslash($_POST);
        // phpcs:enable

        $customer = WC()->customer;

        $mobile = PayerDetails::firstFilled([
            $post[PayerDetails::FIELD_MOBILE] ?? null,
            $order ? $order->get_meta('_wc_other/' . PayerDetails::BLOCK_MOBILE) : null,
            $order ? $order->get_meta(OrderMeta::PAYER_MOBILE) : null,
            $customer ? $customer->get_meta('_wc_other/' . PayerDetails::BLOCK_MOBILE) : null,
        ]);
        $nationalCode = PayerDetails::firstFilled([
            $post[PayerDetails::FIELD_NATIONAL_CODE] ?? null,
            $order ? $order->get_meta('_wc_other/' . PayerDetails::BLOCK_NATIONAL_CODE) : null,
            $order ? $order->get_meta(OrderMeta::PAYER_NATIONAL_CODE) : null,
            $customer ? $customer->get_meta('_wc_other/' . PayerDetails::BLOCK_NATIONAL_CODE) : null,
        ]);

        $firstName = $order ? $order->get_billing_first_name() : (string) ($post['billing_first_name'] ?? '');
        $lastName = $order ? $order->get_billing_last_name() : (string) ($post['billing_last_name'] ?? '');

        return new PayerDetails(
            sanitize_text_field((string) $mobile),
            sanitize_text_field((string) $nationalCode),
            sanitize_text_field((string) $firstName),
            sanitize_text_field((string) $lastName)
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function prefilledPayer(): array
    {
        $saved = WC()->session ? WC()->session->get(self::SESSION_PAYER) : null;
        if (is_array($saved) && count($saved) === 2) {
            return [(string) $saved[0], (string) $saved[1]];
        }

        $customer = WC()->customer;
        if (!$customer) {
            return ['', ''];
        }

        $phone = (string) $customer->get_billing_phone();

        return [
            PayerDetails::firstFilled([$customer->get_meta('_wc_other/' . PayerDetails::BLOCK_MOBILE), preg_match('/^09\d{9}$/', $phone) ? $phone : '']),
            (string) $customer->get_meta('_wc_other/' . PayerDetails::BLOCK_NATIONAL_CODE),
        ];
    }

    /**
     * @param PayerError[] $errors
     */
    private function failure(array $errors): array
    {
        $this->reportErrors($errors);

        return ['result' => 'failure'];
    }

    /**
     * Theme notice (tier C) + inline errors for the next payment_fields() render (tier A).
     *
     * @param PayerError[] $errors
     */
    private function reportErrors(array $errors): void
    {
        foreach ($errors as $error) {
            wc_add_notice($error->notice, 'error', ['id' => 'carbon_' . ($error->field ?? $error->code)]);
        }

        if (WC()->session) {
            WC()->session->set(self::SESSION_ERRORS, array_map(static function (PayerError $e) {
                return $e->toArray();
            }, $errors));
            WC()->session->set('refresh_totals', true);
        }
    }
}
