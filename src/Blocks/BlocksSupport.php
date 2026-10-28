<?php

namespace Carbon\Pay\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Carbon\Pay\Gateway\Gateway;
use Carbon\Pay\GatewayId;
use Carbon\Pay\Plugin;
use Carbon\Pay\Settings;

final class BlocksSupport extends AbstractPaymentMethodType
{
    protected $name = GatewayId::ID;

    public function initialize()
    {
        $this->settings = get_option(Settings::OPTION, []);
    }

    public function is_active()
    {
        return filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN)
            && trim((string) $this->get_setting('merchant_code', '')) !== '';
    }

    public function get_payment_method_script_handles()
    {
        $assets = Plugin::instance()->assets();
        $assets->register();
        $assets->enqueueStyles();

        return ['carbon-pay-blocks'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => Settings::TITLE,
            'button_label' => Gateway::ORDER_BUTTON_TEXT,
            'supports' => ['products'],
        ];
    }
}
