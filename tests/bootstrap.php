<?php

require dirname(__DIR__) . '/vendor/autoload.php';

define('ABSPATH', sys_get_temp_dir() . '/wordpress/');
define('CARBON_PAY_VERSION', '1.0.0');
define('CARBON_PAY_FILE', dirname(__DIR__) . '/carbon-pay-woocommerce.php');
define('CARBON_PAY_DIR', dirname(__DIR__) . '/');
define('CARBON_PAY_URL', 'https://shop.test/wp-content/plugins/carbon-pay-for-woocommerce/');

require __DIR__ . '/Stubs/woocommerce.php';
