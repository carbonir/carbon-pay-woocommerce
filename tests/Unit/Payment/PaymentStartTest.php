<?php

namespace Carbon\Pay\Tests\Unit\Payment;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Payment\PaymentRequestBuilder;
use Carbon\Pay\Payment\PaymentStarter;
use Carbon\Pay\Tests\FakeHttpClient;
use Carbon\Pay\Tests\TestCase;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;

final class PaymentStartTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private function order(): WC_Order
    {
        return new WC_Order([
            'total' => 21396000.0,
            'shipping_total' => 250000.0,
            'items' => [
                new WC_Order_Item_Product([
                    'id' => 318, 'name' => 'گوشی سامسونگ Galaxy A55', 'product_id' => 77,
                    'quantity' => 1, 'subtotal' => 18500000.0, 'total' => 18500000.0, 'total_tax' => 1665000.0,
                ]),
                new WC_Order_Item_Product([
                    'id' => 512, 'name' => 'کاور سیلیکونی A55', 'product_id' => 78,
                    'quantity' => 2, 'subtotal' => 1000000.0, 'total' => 900000.0, 'total_tax' => 81000.0,
                ]),
            ],
            'fees' => [
                new WC_Order_Item_Fee(['id' => 9, 'name' => 'بسته‌بندی', 'total' => 50000.0]),
                new WC_Order_Item_Fee(['id' => 10, 'name' => 'تخفیف', 'total' => -50000.0]),
            ],
        ]);
    }

    public function testBuildsCreatePaymentPayloadInRial(): void
    {
        $payload = (new PaymentRequestBuilder())->build($this->order(), 'https://shop.test/wc-api/wc_carbon_pay/?wc_order=1287', '09121234567', '0012345679');

        self::assertSame('1287', $payload['invoice_reference']);
        self::assertSame(213960000, $payload['invoice_amount']);
        self::assertSame('علی', $payload['payer_first_name']);
        self::assertSame('09121234567', $payload['payer_mobile']);
        self::assertSame('0012345679', $payload['payer_national_code']);
        self::assertSame('پرداخت سفارش #1287', $payload['description']);
        self::assertArrayNotHasKey('merchant_code', $payload, 'CarbonApi adds merchant_code.');

        self::assertSame([
            'reference' => '77', 'name' => 'گوشی سامسونگ Galaxy A55', 'quantity' => 1,
            'unit_price' => 185000000, 'unit_discount' => 0, 'unit_tax_amount' => 16650000, 'is_product' => true,
        ], $payload['items'][0]);

        // 2 × 500,000 with 100,000 total discount → 50,000 per unit.
        self::assertSame(5000000, $payload['items'][1]['unit_price']);
        self::assertSame(500000, $payload['items'][1]['unit_discount']);
        self::assertSame(405000, $payload['items'][1]['unit_tax_amount']);

        // Only the positive fee: CPG rejects negative unit prices.
        self::assertSame('FEE-9', $payload['items'][2]['reference']);
        self::assertFalse($payload['items'][2]['is_product']);

        self::assertSame('SEND-COST', $payload['items'][3]['reference']);
        self::assertSame(2500000, $payload['items'][3]['unit_price']);
        self::assertCount(4, $payload['items']);
    }

    public function testStartCreatesTokenAndRemembersIt(): void
    {
        $http = (new FakeHttpClient())->respond(200, ['data' => ['token' => 'tok-1', 'redirect_url' => 'https://cpg.carbon.ir/payments/tok-1']]);
        $starter = new PaymentStarter(new CarbonApi($http, 'https://api.carbon.ir', 'MRC'), new PaymentRequestBuilder(), 1200);
        $order = $this->order();

        $url = $starter->start($order, 'https://shop.test/cb', '09121234567', '0012345679', self::NOW);

        self::assertSame('https://cpg.carbon.ir/payments/tok-1', $url);
        self::assertSame('tok-1', $order->get_meta(OrderMeta::TOKEN));
        self::assertSame(['tok-1'], $order->get_meta(OrderMeta::TOKENS));
        self::assertSame(self::NOW, $order->get_meta(OrderMeta::TOKEN_CREATED_AT));
        self::assertSame('خریدار به صفحه پرداخت کربن هدایت شد. شناسه پرداخت: tok-1', $order->notes[0]);
        self::assertGreaterThan(0, $order->saves);
    }

    public function testReloadReusesAFreshTokenInsteadOfOpeningASecondCarbonOrder(): void
    {
        $http = (new FakeHttpClient())
            ->respond(200, ['data' => ['token' => 'tok-1', 'redirect_url' => 'https://cpg.carbon.ir/payments/tok-1']])
            ->respond(200, ['data' => ['token' => 'tok-2', 'redirect_url' => 'https://cpg.carbon.ir/payments/tok-2']]);
        $starter = new PaymentStarter(new CarbonApi($http, 'https://api.carbon.ir', 'MRC'), new PaymentRequestBuilder(), 1200);
        $order = $this->order();

        $starter->start($order, 'cb', '09121234567', '0012345679', self::NOW);
        self::assertSame('https://cpg.carbon.ir/payments/tok-1', $starter->start($order, 'cb', '09121234567', '0012345679', self::NOW + 600));
        self::assertCount(1, $http->requests);

        // Past TTL − 2 min: a new token, and the old one is still accepted on callback.
        self::assertSame('https://cpg.carbon.ir/payments/tok-2', $starter->start($order, 'cb', '09121234567', '0012345679', self::NOW + 1100));
        self::assertSame(['tok-1', 'tok-2'], OrderMeta::tokens($order));
    }

    public function testForgettingTheRedirectForcesANewToken(): void
    {
        $http = (new FakeHttpClient())
            ->respond(200, ['data' => ['token' => 'tok-1', 'redirect_url' => 'u1']])
            ->respond(200, ['data' => ['token' => 'tok-2', 'redirect_url' => 'u2']]);
        $starter = new PaymentStarter(new CarbonApi($http, 'https://api.carbon.ir', 'MRC'), new PaymentRequestBuilder(), 1200);
        $order = $this->order();

        $starter->start($order, 'cb', 'm', 'n', self::NOW);
        OrderMeta::forgetRedirect($order);

        self::assertSame('u2', $starter->start($order, 'cb', 'm', 'n', self::NOW + 5));
    }

    public function testApiErrorLeavesOrderUntouched(): void
    {
        $http = (new FakeHttpClient())->respond(422, ['type' => 'validation_error', 'errors' => ['national_code' => ['x']]]);
        $starter = new PaymentStarter(new CarbonApi($http, 'https://api.carbon.ir', 'MRC'), new PaymentRequestBuilder(), 1200);
        $order = $this->order();

        try {
            $starter->start($order, 'cb', 'm', 'n', self::NOW);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(ApiException::KIND_VALIDATION, $e->kind());
        }

        self::assertSame('', $order->get_meta(OrderMeta::TOKEN));
        self::assertSame([], $order->notes);
    }

    public function testLegacyTokenIsReadUntilMigrated(): void
    {
        $order = new WC_Order(['meta' => [OrderMeta::LEGACY_TOKEN => 'gsm-tok']]);

        self::assertSame('gsm-tok', OrderMeta::token($order));
        self::assertSame(['gsm-tok'], OrderMeta::tokens($order));

        $order->update_meta_data(OrderMeta::TOKEN, 'carbon-tok');
        self::assertSame('carbon-tok', OrderMeta::token($order));
    }
}
