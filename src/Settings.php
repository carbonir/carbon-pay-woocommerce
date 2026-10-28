<?php

namespace Carbon\Pay;

/**
 * Read-only access to the gateway settings without instantiating the gateway.
 *
 * The merchant configures only the switch and the merchant code. The title is fixed Carbon copy,
 * and the plugin always talks to production; developers point it elsewhere with `carbon_pay_api_base`.
 */
final class Settings
{
    public const OPTION = 'woocommerce_WC_Carbon_Pay_settings';
    public const LEGACY_OPTION = 'woocommerce_WC_GSMPay_settings';

    public const TITLE = 'کربن — پرداخت با قدرت خرید';
    public const API_BASE = 'https://api.carbon.ir';

    /** Keys earlier versions saved (merchant-editable title and description, test environment). */
    public const OBSOLETE_KEYS = ['title', 'description', 'environment'];

    /** @var array */
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function load(): self
    {
        $values = get_option(self::OPTION, []);

        return new self(is_array($values) ? $values : []);
    }

    public function isEnabled(): bool
    {
        return ($this->values['enabled'] ?? 'no') === 'yes';
    }

    public function merchantCode(): string
    {
        return trim((string) ($this->values['merchant_code'] ?? ''));
    }

    public static function apiBaseUrl(): string
    {
        $base = self::API_BASE;
        if (function_exists('apply_filters')) {
            $base = (string) apply_filters('carbon_pay_api_base', $base);
        }

        return rtrim($base, '/');
    }
}
