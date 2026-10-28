<?php

namespace Carbon\Pay\Support;

/**
 * Converts Persian (U+06F0–U+06F9) and Arabic-Indic (U+0660–U+0669) digits to ASCII.
 */
final class PersianText
{
    private const MAP = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * @param mixed $value
     */
    public static function toEnglishDigits($value): string
    {
        if (!is_scalar($value) || is_bool($value)) {
            return '';
        }

        return strtr((string) $value, self::MAP);
    }

    /**
     * Normalises user-typed identifiers (mobile, national code): ASCII digits, no spaces or dashes.
     *
     * @param mixed $value
     */
    public static function normalizeDigits($value): string
    {
        return (string) preg_replace('/[\s\-\x{200c}]+/u', '', self::toEnglishDigits($value));
    }
}
