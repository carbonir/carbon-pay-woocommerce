<?php
/**
 * Plugin Name:          Carbon Pay for WooCommerce
 * Plugin URI:           https://carbon.ir
 * Description:          Let customers pay with their Carbon purchasing power (cash, gold and silver, credit and instalments) through the Carbon CPG payment gateway.
 * Version:              1.0.0
 * Author:               سکوی مدیریت آرمان
 * Author URI:           https://carbon.ir
 * Text Domain:          carbon-pay-for-woocommerce
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.9
 * WC tested up to:      11.2
 * License:              GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CARBON_PAY_VERSION', '1.0.0');
define('CARBON_PAY_FILE', __FILE__);
define('CARBON_PAY_DIR', plugin_dir_path(__FILE__));
define('CARBON_PAY_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function ($class) {
    $prefix = 'Carbon\\Pay\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $file = CARBON_PAY_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, [Carbon\Pay\Plugin::class, 'activate']);

Carbon\Pay\Plugin::instance()->boot();
