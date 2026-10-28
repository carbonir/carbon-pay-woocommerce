<?php

namespace Carbon\Pay\Support;

/**
 * Carbon receives every amount in Rial. Store amounts are in the WooCommerce currency.
 */
final class Money
{
    private const RIAL_FACTORS = [
        'IRR' => 1,
        'IRT' => 10,
        'IRHR' => 100,
        'IRHT' => 1000,
    ];

    /**
     * @param float|int|string $amount
     */
    public static function toRial(string $currency, $amount): int
    {
        return (int) round((float) $amount * self::factor($currency));
    }

    public static function fromRial(string $currency, int $rial): float
    {
        return $rial / self::factor($currency);
    }

    public static function isSupported(string $currency): bool
    {
        return isset(self::RIAL_FACTORS[strtoupper($currency)]);
    }

    private static function factor(string $currency): int
    {
        return self::RIAL_FACTORS[strtoupper($currency)] ?? 1;
    }
}
