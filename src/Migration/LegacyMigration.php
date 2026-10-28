<?php

namespace Carbon\Pay\Migration;

use Carbon\Pay\GatewayId;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Settings;
use WC_Order;

/**
 * Moves a GSMPay (1.x) installation to Carbon Pay:
 *  - settings: woocommerce_WC_GSMPay_settings → woocommerce_WC_Carbon_Pay_settings
 *    (enabled + merchant code);
 *  - orders: payment method WC_GSMPay → WC_Carbon_Pay and gsmpay_token → carbon_token, in batches,
 *    so refunds and callbacks keep working for orders placed before the upgrade.
 * The legacy option and meta are left in place for rollback.
 *
 * Also drops settings keys that pre-1.0 builds let merchants edit and 1.0.0 fixed (title, description,
 * environment), so nothing stale is left behind.
 */
final class LegacyMigration
{
    public const VERSION_OPTION = 'carbon_pay_version';
    public const ORDERS_DONE_OPTION = 'carbon_pay_orders_migrated';
    public const BATCH_HOOK = 'carbon_pay_migrate_orders';
    public const BATCH_SIZE = 50;

    public function register(): void
    {
        add_action(self::BATCH_HOOK, [$this, 'runOrderBatch']);
        add_action('init', [$this, 'maybeRun']);
    }

    public function maybeRun(): void
    {
        if (get_option(self::VERSION_OPTION) === CARBON_PAY_VERSION) {
            return;
        }

        $this->migrateSettings();
        $this->removeObsoleteSettings();

        if (get_option(self::ORDERS_DONE_OPTION) !== 'yes') {
            $this->scheduleOrderBatch();
        }

        update_option(self::VERSION_OPTION, CARBON_PAY_VERSION);
    }

    /**
     * @return bool True when legacy settings were copied.
     */
    public function migrateSettings(): bool
    {
        if (get_option(Settings::OPTION) !== false) {
            return false;
        }

        $legacy = get_option(Settings::LEGACY_OPTION);
        if (!is_array($legacy)) {
            return false;
        }

        update_option(Settings::OPTION, [
            'enabled' => ($legacy['enabled'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'merchant_code' => trim((string) ($legacy['merchant_code'] ?? '')),
        ]);

        return true;
    }

    /**
     * @return bool True when the saved settings changed.
     */
    public function removeObsoleteSettings(): bool
    {
        $settings = get_option(Settings::OPTION);
        if (!is_array($settings)) {
            return false;
        }

        $cleaned = array_diff_key($settings, array_flip(Settings::OBSOLETE_KEYS));
        if ($cleaned === $settings) {
            return false;
        }

        update_option(Settings::OPTION, $cleaned);

        return true;
    }

    /**
     * @return int Orders migrated in this batch.
     */
    public function runOrderBatch(): int
    {
        $orders = wc_get_orders([
            'payment_method' => GatewayId::LEGACY_ID,
            'limit' => self::BATCH_SIZE,
            'orderby' => 'ID',
            'order' => 'ASC',
            'type' => 'shop_order',
        ]);

        foreach ($orders as $order) {
            if ($order instanceof WC_Order) {
                self::migrateOrder($order);
            }
        }

        if (count($orders) >= self::BATCH_SIZE) {
            $this->scheduleOrderBatch();
        } else {
            update_option(self::ORDERS_DONE_OPTION, 'yes');
        }

        return count($orders);
    }

    public static function migrateOrder(WC_Order $order): void
    {
        $legacyToken = (string) $order->get_meta(OrderMeta::LEGACY_TOKEN);
        if ($legacyToken !== '' && (string) $order->get_meta(OrderMeta::TOKEN) === '') {
            $order->update_meta_data(OrderMeta::TOKEN, $legacyToken);
            $order->update_meta_data(OrderMeta::TOKENS, [$legacyToken]);
        }

        if ($order->is_paid() && (string) $order->get_meta(OrderMeta::STATUS) === '') {
            $order->update_meta_data(OrderMeta::STATUS, OrderMeta::STATUS_PAID);
        }

        $order->set_payment_method(GatewayId::ID);
        $order->set_payment_method_title(Settings::TITLE);
        $order->save();
    }

    private function scheduleOrderBatch(): void
    {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::BATCH_HOOK, [], 'carbon-pay');
        } elseif (!wp_next_scheduled(self::BATCH_HOOK)) {
            wp_schedule_single_event(time() + 30, self::BATCH_HOOK);
        }
    }
}
