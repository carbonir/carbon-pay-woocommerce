<?php

namespace Carbon\Pay;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Carbon\Pay\Admin\ConnectionCheck;
use Carbon\Pay\Admin\OrderScreen;
use Carbon\Pay\Admin\SettingsPage;
use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Api\HttpClient;
use Carbon\Pay\Api\WpHttpClient;
use Carbon\Pay\Blocks\BlocksSupport;
use Carbon\Pay\Blocks\CheckoutFields;
use Carbon\Pay\Currency\Currencies;
use Carbon\Pay\Frontend\Handoff;
use Carbon\Pay\Frontend\StatusView;
use Carbon\Pay\Gateway\Gateway;
use Carbon\Pay\Migration\LegacyMigration;
use Carbon\Pay\Payment\PaymentRequestBuilder;
use Carbon\Pay\Payment\PaymentStarter;
use Carbon\Pay\Payment\ReturnHandler;
use Carbon\Pay\Payment\ReturnResolver;
use Carbon\Pay\Payment\ReturnResponse;
use WC_Order;

final class Plugin
{
    /** Default lifetime of a Carbon payment token (CARBON_GATEWAY_ONLINE_TIMEOUT, 20 min). */
    public const TOKEN_TTL = 1200;

    /** @var self|null */
    private static $instance;

    /** @var Assets|null */
    private $assets;

    /** @var Handoff|null */
    private $handoff;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        add_action('before_woocommerce_init', [$this, 'declareCompatibility']);
        add_action('plugins_loaded', [$this, 'init'], 11);
    }

    public static function activate(): void
    {
        update_option(LegacyMigration::VERSION_OPTION, '');
    }

    public function declareCompatibility(): void
    {
        if (class_exists(FeaturesUtil::class)) {
            FeaturesUtil::declare_compatibility('custom_order_tables', CARBON_PAY_FILE, true);
            FeaturesUtil::declare_compatibility('cart_checkout_blocks', CARBON_PAY_FILE, true);
        }
    }

    public function init(): void
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'missingWooCommerceNotice']);
            add_action('admin_init', [$this, 'deactivateSelf']);

            return;
        }

        (new Currencies())->register();
        (new LegacyMigration())->register();

        add_filter('woocommerce_payment_gateways', static function ($gateways) {
            $gateways[] = Gateway::class;

            return $gateways;
        });

        // woocommerce_blocks_loaded has already fired by plugins_loaded:11; the registry fires this later.
        add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry) {
            $registry->register(new BlocksSupport());
        });
        add_action('woocommerce_init', [new CheckoutFields(), 'register']);

        add_action('woocommerce_api_' . strtolower(GatewayId::ID), [$this, 'handleReturn']);
        if (!class_exists('WC_GSMPay')) {
            // Callbacks of payments started by the GSMPay plugin before the upgrade.
            add_action('woocommerce_api_' . strtolower(GatewayId::LEGACY_ID), [$this, 'handleReturn']);
        }

        $this->assets()->hook();
        $this->handoff()->register();
        (new StatusView($this->tokenTtl()))->register();
        (new SettingsPage())->register();
        (new ConnectionCheck())->register();
        (new OrderScreen())->register();
    }

    public function assets(): Assets
    {
        if ($this->assets === null) {
            $this->assets = new Assets();
        }

        return $this->assets;
    }

    public function handoff(): Handoff
    {
        if ($this->handoff === null) {
            $this->handoff = new Handoff();
        }

        return $this->handoff;
    }

    public function tokenTtl(): int
    {
        return max(60, (int) apply_filters('carbon_pay_token_ttl', self::TOKEN_TTL));
    }

    /**
     * Null when no merchant code is configured.
     */
    public function api(): ?CarbonApi
    {
        $settings = Settings::load();
        if ($settings->merchantCode() === '') {
            return null;
        }

        return $this->apiFor($settings->merchantCode());
    }

    public function apiFor(string $merchantCode): CarbonApi
    {
        return new CarbonApi($this->httpClient(), Settings::apiBaseUrl(), $merchantCode);
    }

    public function paymentStarter(): ?PaymentStarter
    {
        $api = $this->api();

        return $api ? new PaymentStarter($api, new PaymentRequestBuilder(), $this->tokenTtl()) : null;
    }


    public function handleReturn(): void
    {
        $settings = Settings::load();
        $api = $this->apiFor($settings->merchantCode());
        $handler = new ReturnHandler(new ReturnResolver($api, $this->tokenTtl()));

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT'])) : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- authorised by the Carbon token.
        $response = $handler->handle(wp_unslash($_GET), self::isBrowserRequest($accept), time());

        $this->sendReturnResponse($response);
    }

    /**
     * carbon-service's webhook client sends no Accept: text/html; browsers always do.
     */
    public static function isBrowserRequest(string $accept): bool
    {
        return stripos($accept, 'text/html') !== false;
    }

    private function sendReturnResponse(ReturnResponse $response): void
    {
        if ($response->type === 'json') {
            wp_send_json($response->body, $response->status);
        }

        if (WC()->session) {
            foreach ($response->notices as [$message, $type]) {
                wc_add_notice($message, $type);
            }
        }

        wp_safe_redirect($response->url);
        exit;
    }

    public static function callbackBaseUrl(): string
    {
        return WC()->api_request_url(strtolower(GatewayId::ID));
    }

    public static function callbackUrl(WC_Order $order): string
    {
        return add_query_arg([
            'wc_order' => $order->get_id(),
            'key' => $order->get_order_key(),
        ], self::callbackBaseUrl());
    }

    public static function log(string $message, array $context = []): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($message, $context + ['source' => 'carbon-pay']);
        }
    }

    /**
     * Logs type/status/message only; never the request payload (mobile, national code).
     */
    public static function logApiError(string $operation, ApiException $e, ?WC_Order $order = null): void
    {
        self::log(sprintf('Carbon %s failed: [%s] %s', $operation, $e->type() ?? $e->kind(), $e->getMessage()), [
            'order' => $order ? $order->get_id() : null,
            'http_status' => $e->httpStatus(),
            'fields' => array_keys($e->fieldErrors()),
        ]);
    }

    public function missingWooCommerceNotice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $installed = file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
        $url = $installed
            ? wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php'), 'activate-plugin_woocommerce/woocommerce.php')
            : self_admin_url('plugin-install.php?s=woocommerce&tab=search&type=term');

        printf(
            '<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
            'برای استفاده از افزونه کربن، ووکامرس باید نصب و فعال باشد. افزونه کربن غیرفعال شد.',
            esc_url($url),
            $installed ? 'فعال‌سازی ووکامرس' : 'نصب ووکامرس'
        );
    }

    public function deactivateSelf(): void
    {
        if (current_user_can('activate_plugins')) {
            deactivate_plugins(plugin_basename(CARBON_PAY_FILE));
        }
    }

    private function httpClient(): HttpClient
    {
        $client = apply_filters('carbon_pay_http_client', null);

        return $client instanceof HttpClient ? $client : new WpHttpClient();
    }
}
