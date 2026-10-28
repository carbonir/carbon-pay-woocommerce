<?php

namespace Carbon\Pay\Currency;

/**
 * Iranian currencies for WooCommerce. Carbon receives Rial; see Support\Money for the factors.
 */
final class Currencies
{
    private const LABELS = [
        'IRR' => 'ریال',
        'IRT' => 'تومان',
        'IRHR' => 'هزار ریال',
        'IRHT' => 'هزار تومان',
    ];

    public function register(): void
    {
        add_filter('woocommerce_currencies', [$this, 'addCurrencies']);
        add_filter('woocommerce_currency_symbol', [$this, 'symbol'], 10, 2);
    }

    /**
     * @param array<string, string> $currencies
     * @return array<string, string>
     */
    public function addCurrencies($currencies): array
    {
        return array_merge((array) $currencies, self::LABELS);
    }

    /**
     * @param string $symbol
     * @param string $currency
     */
    public function symbol($symbol, $currency): string
    {
        return self::LABELS[$currency] ?? (string) $symbol;
    }
}
