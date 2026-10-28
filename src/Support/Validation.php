<?php

namespace Carbon\Pay\Support;

/**
 * Mirrors the rules carbon-service applies in CreateOnlineValidator, so the payer
 * gets an inline error instead of a round trip to the API.
 */
final class Validation
{
    /** Same pattern as carbon-service App\Domains\Users\Rules\Mobile. */
    public static function mobile(string $mobile): bool
    {
        return (bool) preg_match('/^09\d{9}$/', $mobile);
    }

    /**
     * Iranian personal national code: 10 digits, checksum, not all digits equal.
     */
    public static function nationalCode(string $code): bool
    {
        if (!preg_match('/^\d{10}$/', $code)) {
            return false;
        }

        if (count(array_unique(str_split($code))) === 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return $remainder < 2 ? $check === $remainder : $check === 11 - $remainder;
    }

    /**
     * Same pattern as carbon-core ATHK\Core\Rules\Alphabet, used for payer_first_name / payer_last_name:
     * Arabic-block letters, digits, ZWNJ, harakat and whitespace.
     */
    public static function personName(string $name): bool
    {
        if (trim($name) === '') {
            return false;
        }

        return (bool) preg_match(
            '/^[0-9\x{600}-\x{6FF}\x{200c}\x{064b}\x{064d}\x{064c}\x{064e}\x{064f}\x{0650}\x{0651}\s]+$/u',
            $name
        );
    }
}
