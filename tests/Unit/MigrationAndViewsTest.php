<?php

namespace Carbon\Pay\Tests\Unit;

use Brain\Monkey\Functions;
use Carbon\Pay\Currency\Currencies;
use Carbon\Pay\Frontend\StatusView;
use Carbon\Pay\GatewayId;
use Carbon\Pay\Migration\LegacyMigration;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Payment\ReturnOutcome;
use Carbon\Pay\Plugin;
use Carbon\Pay\Settings;
use Carbon\Pay\Tests\TestCase;
use WC_Order;

final class MigrationAndViewsTest extends TestCase
{
    public function testLegacySettingsAreCopiedWithCarbonCopy(): void
    {
        $this->options[Settings::LEGACY_OPTION] = [
            'enabled' => 'yes',
            'title' => 'جی‌اس‌ام پی - درگاه پرداخت اعتباری (‌اقساطی)',
            'description' => 'پرداخت اقساطی از طریق درگاه جی‌اس‌ام پی',
            'merchant_code' => ' MRC-104582 ',
        ];

        self::assertTrue((new LegacyMigration())->migrateSettings());
        self::assertSame([
            'enabled' => 'yes',
            'merchant_code' => 'MRC-104582',
        ], $this->options[Settings::OPTION]);
        self::assertArrayHasKey(Settings::LEGACY_OPTION, $this->options, 'Kept for rollback.');
    }

    public function testExistingCarbonSettingsAreNeverOverwritten(): void
    {
        $this->options[Settings::OPTION] = ['merchant_code' => 'NEW'];
        $this->options[Settings::LEGACY_OPTION] = ['merchant_code' => 'OLD'];

        self::assertFalse((new LegacyMigration())->migrateSettings());
        self::assertSame(['merchant_code' => 'NEW'], $this->options[Settings::OPTION]);
    }

    public function testObsoleteSettingsAreRemoved(): void
    {
        $this->options[Settings::OPTION] = [
            'enabled' => 'yes',
            'title' => 'عنوان دلخواه',
            'description' => 'توضیح دلخواه',
            'merchant_code' => 'MRC',
            'environment' => 'stage',
        ];

        $migration = new LegacyMigration();
        self::assertTrue($migration->removeObsoleteSettings());
        self::assertSame(['enabled' => 'yes', 'merchant_code' => 'MRC'], $this->options[Settings::OPTION]);
        self::assertFalse($migration->removeObsoleteSettings(), 'Nothing left to remove.');
    }

    public function testNothingToMigrateOnFreshInstall(): void
    {
        self::assertFalse((new LegacyMigration())->migrateSettings());
        self::assertArrayNotHasKey(Settings::OPTION, $this->options);
    }

    public function testLegacyOrderMovesToCarbonGateway(): void
    {
        $order = new WC_Order([
            'status' => 'processing',
            'payment_method' => GatewayId::LEGACY_ID,
            'meta' => [OrderMeta::LEGACY_TOKEN => 'gsm-tok'],
        ]);

        LegacyMigration::migrateOrder($order);

        self::assertSame(GatewayId::ID, $order->get_payment_method());
        self::assertSame(Settings::TITLE, $order->payment_method_title);
        self::assertSame('gsm-tok', $order->get_meta(OrderMeta::TOKEN));
        self::assertSame(['gsm-tok'], $order->get_meta(OrderMeta::TOKENS));
        self::assertSame(OrderMeta::STATUS_PAID, $order->get_meta(OrderMeta::STATUS));
        self::assertSame('gsm-tok', $order->get_meta(OrderMeta::LEGACY_TOKEN), 'Kept for rollback.');
        self::assertSame(1, $order->saves);
    }

    public function testOrderBatchReschedulesUntilDone(): void
    {
        $full = array_map(static function ($i) {
            return new WC_Order(['id' => $i, 'payment_method' => GatewayId::LEGACY_ID]);
        }, range(1, LegacyMigration::BATCH_SIZE));

        Functions\expect('wc_get_orders')->twice()->andReturn($full, [new WC_Order(['payment_method' => GatewayId::LEGACY_ID])]);
        Functions\expect('as_enqueue_async_action')->once()->with(LegacyMigration::BATCH_HOOK, [], 'carbon-pay');

        $migration = new LegacyMigration();
        self::assertSame(LegacyMigration::BATCH_SIZE, $migration->runOrderBatch());
        self::assertArrayNotHasKey(LegacyMigration::ORDERS_DONE_OPTION, $this->options);

        self::assertSame(1, $migration->runOrderBatch());
        self::assertSame('yes', $this->options[LegacyMigration::ORDERS_DONE_OPTION]);
    }

    public function testGatewayOwnsLegacyOrders(): void
    {
        self::assertTrue(GatewayId::owns(new WC_Order(['payment_method' => GatewayId::LEGACY_ID])));
        self::assertTrue(GatewayId::owns(new WC_Order(['payment_method' => GatewayId::ID])));
        self::assertFalse(GatewayId::owns(new WC_Order(['payment_method' => 'cod'])));
    }

    /**
     * Copy, tone and actions from the «Carbon WooCommerce Gateway» design (1f–1l).
     */
    public function testStatusVariants(): void
    {
        $paid = StatusView::variant(ReturnOutcome::PAID, 20);
        self::assertSame('success', $paid['tone']);
        self::assertSame('خرید شما با کربن ثبت شد', $paid['title']);
        self::assertSame(['done', 'solid', 'done', 'solid', 'success'], $paid['journey']);
        self::assertSame(['app'], array_column($paid['actions'], 'target'));

        $cancelled = StatusView::variant(ReturnOutcome::CANCELLED, 20);
        self::assertSame('neutral', $cancelled['tone'], 'The payer chose to cancel; nothing went wrong.');
        self::assertSame('پرداخت را در کربن لغو کردید', $cancelled['title']);
        self::assertSame(['other', 'pay'], array_column($cancelled['actions'], 'target'));
        self::assertSame('مبلغی از حساب شما کسر نشده است.', $cancelled['note']['text']);

        $api = StatusView::variant(ReturnOutcome::API_ERROR, 20);
        self::assertSame('warning', $api['tone']);
        self::assertSame(['pay', 'recheck'], array_column($api['actions'], 'target'));
        self::assertSame(['outline', 'primary'], array_column($api['actions'], 'style'), 'Re-check is primary, pay again is demoted.');
        self::assertSame('warning', $api['note']['tone']);

        $pending = StatusView::variant(ReturnOutcome::PENDING, 20);
        self::assertSame('spinner', $pending['icon']);
        self::assertSame(['recheck'], array_column($pending['actions'], 'target'), 'No pay-again while Carbon is verifying.');

        $expired = StatusView::variant(ReturnOutcome::EXPIRED, 20);
        self::assertStringContainsString('فقط ۲۰ دقیقه معتبر است', $expired['text']);
        self::assertStringContainsString('مدت محدودی', StatusView::variant(ReturnOutcome::EXPIRED, 0)['text']);

        self::assertSame('error', StatusView::variant(ReturnOutcome::INVALID_TOKEN, 20)['tone']);
        self::assertSame('error', StatusView::variant(ReturnOutcome::UNVERIFIED, 20)['tone']);
        self::assertNull(StatusView::variant(ReturnOutcome::ALREADY_DONE, 20));
        self::assertNull(StatusView::variant('bogus', 20));

        foreach ([ReturnOutcome::PAID, ReturnOutcome::PENDING, ReturnOutcome::API_ERROR, ReturnOutcome::CANCELLED, ReturnOutcome::EXPIRED, ReturnOutcome::UNVERIFIED, ReturnOutcome::INVALID_TOKEN] as $outcome) {
            self::assertCount(5, StatusView::variant($outcome, 20)['journey'], $outcome);
        }
    }

    public function testFailedOutcomes(): void
    {
        self::assertTrue(ReturnOutcome::marksFailed(ReturnOutcome::CANCELLED));
        self::assertTrue(ReturnOutcome::marksFailed(ReturnOutcome::UNVERIFIED));
        self::assertTrue(ReturnOutcome::marksFailed(ReturnOutcome::EXPIRED));
        self::assertFalse(ReturnOutcome::marksFailed(ReturnOutcome::PENDING));
        self::assertFalse(ReturnOutcome::marksFailed(ReturnOutcome::API_ERROR));
        self::assertFalse(ReturnOutcome::marksFailed(ReturnOutcome::INVALID_TOKEN));
    }

    public function testBrowserDetection(): void
    {
        self::assertTrue(Plugin::isBrowserRequest('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'));
        self::assertFalse(Plugin::isBrowserRequest(''));
        self::assertFalse(Plugin::isBrowserRequest('application/json'));
        self::assertFalse(Plugin::isBrowserRequest('*/*'));
    }

    public function testCurrencies(): void
    {
        $currencies = new Currencies();

        self::assertSame(['USD' => 'Dollar', 'IRR' => 'ریال', 'IRT' => 'تومان', 'IRHR' => 'هزار ریال', 'IRHT' => 'هزار تومان'], $currencies->addCurrencies(['USD' => 'Dollar']));
        self::assertSame('تومان', $currencies->symbol('&#xfdfc;', 'IRT'));
        self::assertSame('$', $currencies->symbol('$', 'USD'));
    }

    public function testSettingsDefaults(): void
    {
        $settings = new Settings([]);
        self::assertFalse($settings->isEnabled());
        self::assertSame('', $settings->merchantCode());

        $saved = new Settings(['enabled' => 'yes', 'merchant_code' => ' MRC ', 'environment' => 'stage', 'title' => 'x']);
        self::assertTrue($saved->isEnabled());
        self::assertSame('MRC', $saved->merchantCode());
        self::assertSame('https://api.carbon.ir', Settings::apiBaseUrl(), 'A saved test environment is ignored.');
    }
}
