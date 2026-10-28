<?php

namespace Carbon\Pay\Order;

use WC_Order;

/**
 * Order meta written by the plugin. Always read and written through the order object (HPOS-safe).
 */
final class OrderMeta
{
    public const TOKEN = 'carbon_token';
    public const TOKENS = 'carbon_tokens';
    public const REDIRECT_URL = 'carbon_redirect_url';
    public const TOKEN_CREATED_AT = 'carbon_token_created_at';
    public const VERIFIED_AT = 'carbon_verified_at';
    public const STATUS = 'carbon_status';
    public const PAYER_MOBILE = 'payer_mobile';
    public const PAYER_NATIONAL_CODE = 'payer_national_code';

    /** Written by the GSMPay plugin (1.x). Read as a fallback until LegacyMigration has run. */
    public const LEGACY_TOKEN = 'gsmpay_token';

    public const STATUS_PAID = 'paid';
    /** Written by refunds in pre-1.0 builds; still read so those orders show as refunded. */
    public const STATUS_REFUND = 'refund';

    public static function token(WC_Order $order): string
    {
        $token = (string) $order->get_meta(self::TOKEN);

        return $token !== '' ? $token : (string) $order->get_meta(self::LEGACY_TOKEN);
    }

    /**
     * Every token issued for this order. A payer who abandons a payment and pays again
     * gets a new token, but the earlier Carbon order may still be completed (and called back).
     *
     * @return string[]
     */
    public static function tokens(WC_Order $order): array
    {
        $tokens = $order->get_meta(self::TOKENS);
        $tokens = is_array($tokens) ? $tokens : [];

        $current = self::token($order);
        if ($current !== '') {
            $tokens[] = $current;
        }

        return array_values(array_unique(array_filter(array_map('strval', $tokens))));
    }

    public static function rememberToken(WC_Order $order, string $token, string $redirectUrl, int $now): void
    {
        $tokens = self::tokens($order);
        $tokens[] = $token;

        $order->update_meta_data(self::TOKEN, $token);
        $order->update_meta_data(self::TOKENS, array_values(array_unique($tokens)));
        $order->update_meta_data(self::REDIRECT_URL, $redirectUrl);
        $order->update_meta_data(self::TOKEN_CREATED_AT, $now);
    }

    public static function forgetRedirect(WC_Order $order): void
    {
        $order->delete_meta_data(self::REDIRECT_URL);
    }
}
