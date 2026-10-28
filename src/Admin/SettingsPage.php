<?php

namespace Carbon\Pay\Admin;

use Carbon\Pay\Gateway\Gateway;
use Carbon\Pay\GatewayId;
use Carbon\Pay\Settings;

/**
 * Plugins-screen link and the admin notices around configuration.
 */
final class SettingsPage
{
    public function register(): void
    {
        add_filter('plugin_action_links_' . plugin_basename(CARBON_PAY_FILE), [$this, 'actionLinks']);
        add_action('admin_notices', [$this, 'notices']);
        add_action('admin_init', [$this, 'redirectLowercaseSection']);
    }

    /**
     * The settings section WooCommerce matches to this gateway. WooCommerce lowercases ?section= and then
     * compares it case-sensitively with the gateway id (WC_Carbon_Pay) or sanitize_title() of its class,
     * so only the class form renders and saves the settings.
     */
    public static function section(): string
    {
        return sanitize_title(Gateway::class);
    }

    public static function url(): string
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . self::section());
    }

    public static function isCurrentScreen(): bool
    {
        return self::currentSection() === self::section();
    }

    /**
     * ?section=wc_carbon_pay (links and bookmarks from pre-1.0 builds) renders an empty page; send it to the working one.
     */
    public function redirectLowercaseSection(): void
    {
        if (self::currentSection() === strtolower(GatewayId::ID) && current_user_can('manage_woocommerce')) {
            wp_safe_redirect(self::url());
            exit;
        }
    }

    private static function currentSection(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen check.
        if (!isset($_GET['page'], $_GET['section']) || $_GET['page'] !== 'wc-settings') {
            return '';
        }

        return sanitize_title(wp_unslash($_GET['section']));
        // phpcs:enable
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function actionLinks($links): array
    {
        array_unshift($links, sprintf('<a href="%s">%s</a>', esc_url(self::url()), 'تنظیمات'));

        return $links;
    }

    public function notices(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (class_exists('WC_GSMPay')) {
            $this->notice(
                'warning',
                '<strong>کربن:</strong> افزونه قدیمی جی‌اس‌ام پی هنوز فعال است. تنظیمات و سفارش‌های آن به کربن منتقل شده‌اند؛ آن را از صفحه افزونه‌ها غیرفعال کنید.'
            );
        }

        $settings = Settings::load();
        if (self::isCurrentScreen() && $settings->isEnabled() && $settings->merchantCode() === '') {
            $this->notice(
                'warning',
                '<strong>کربن:</strong> کد پذیرنده وارد نشده است. تا وارد نشود، خریداران نمی‌توانند با کربن پرداخت کنند.'
            );
        }
    }


    private function notice(string $type, string $html): void
    {
        printf('<div class="notice notice-%s"><p>%s</p></div>', esc_attr($type), wp_kses($html, ['strong' => []]));
    }
}
