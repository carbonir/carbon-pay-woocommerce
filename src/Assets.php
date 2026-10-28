<?php

namespace Carbon\Pay;

use Carbon\Pay\Admin\SettingsPage;

/**
 * Stylesheets and scripts. carbon-pay.css is the only shipped stylesheet (scoped under .carbon-pay);
 * Phosphor icons are a local subset (bin/build-icons.mjs), never a CDN.
 */
final class Assets
{
    /** @var bool */
    private $registered = false;

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $url = CARBON_PAY_URL . 'assets/';
        $version = CARBON_PAY_VERSION;

        wp_register_style('carbon-pay-icons', $url . 'vendor/phosphor/phosphor.css', [], $version);
        wp_register_style('carbon-pay', $url . 'carbon-pay.css', ['carbon-pay-icons'], $version);

        wp_register_script('carbon-pay-handoff', $url . 'js/handoff.js', [], $version, true);
        wp_register_script('carbon-pay-order-status', $url . 'js/order-status.js', [], $version, true);
        wp_register_script('carbon-pay-admin', $url . 'js/admin.js', [], $version, true);
        wp_register_script(
            'carbon-pay-blocks',
            $url . 'js/checkout-blocks.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
            $version,
            true
        );
    }

    public function hook(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'onFrontend']);
        add_action('admin_enqueue_scripts', [$this, 'onAdmin']);
    }

    public function onFrontend(): void
    {
        $this->register();

        $isCarbonPage = (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('view-order'));

        if ($isCarbonPage) {
            $this->enqueueStyles();
        }
    }

    /**
     * @param string $hook
     */
    public function onAdmin($hook): void
    {
        $this->register();

        $onSettings = $hook === 'woocommerce_page_wc-settings' && SettingsPage::isCurrentScreen();

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $onOrder = ($hook === 'post.php' && $screen && $screen->post_type === 'shop_order')
            || $hook === 'woocommerce_page_wc-orders';

        if ($onSettings || $onOrder) {
            $this->enqueueAdmin();
        }
    }

    public function enqueueStyles(): void
    {
        $this->register();
        wp_enqueue_style('carbon-pay');
    }

    public function enqueueFrontend(): void
    {
        $this->enqueueStyles();
    }

    public function enqueueAdmin(): void
    {
        $this->enqueueStyles();
        wp_enqueue_script('carbon-pay-admin');
    }
}
