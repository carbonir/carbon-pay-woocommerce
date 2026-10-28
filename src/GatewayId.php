<?php

namespace Carbon\Pay;

use WC_Order;

/**
 * Gateway identifiers, kept apart from Gateway\Gateway so code that only needs the id does not
 * load WC_Payment_Gateway.
 */
final class GatewayId
{
    public const ID = 'WC_Carbon_Pay';

    /** Gateway id of the GSMPay plugin (1.x). Orders are moved to ID by LegacyMigration. */
    public const LEGACY_ID = 'WC_GSMPay';

    public static function owns(WC_Order $order): bool
    {
        return in_array($order->get_payment_method(), [self::ID, self::LEGACY_ID], true);
    }
}
