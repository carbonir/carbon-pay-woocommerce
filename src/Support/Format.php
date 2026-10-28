<?php

namespace Carbon\Pay\Support;

/**
 * Display formatting per the Carbon design system: Persian digits, «٬» thousands separator.
 */
final class Format
{
    private const DIGITS = ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'];

    public static function persianDigits(string $value): string
    {
        return strtr($value, self::DIGITS);
    }

    /**
     * @param float|int|string $amount
     */
    public static function number($amount): string
    {
        return self::persianDigits(number_format((float) round((float) $amount), 0, '.', '٬'));
    }

    /**
     * «۲۱٬۳۹۶٬۰۰۰ تومان»
     *
     * @param float|int|string $amount
     */
    public static function money($amount, string $unit): string
    {
        return trim(self::number($amount) . ' ' . $unit);
    }

    /**
     * «9f3c1a7e-…4a19» for long payment tokens.
     */
    public static function shortToken(string $token): string
    {
        if (strlen($token) <= 16) {
            return $token;
        }

        return substr($token, 0, 9) . '…' . substr($token, -4);
    }
}
