<?php

namespace Carbon\Pay\Tests\Unit\Payment;

use Brain\Monkey\Functions;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Payment\ReturnHandler;
use Carbon\Pay\Payment\ReturnOutcome;
use Carbon\Pay\Payment\ReturnResolver;
use Carbon\Pay\Tests\FakeHttpClient;
use Carbon\Pay\Tests\TestCase;
use WC_Order;

/**
 * Callback handling. The flow fixes from CARBON-CPG-DISCOVERY.md F2–F4 are pinned here.
 */
final class ReturnFlowTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /** @var FakeHttpClient */
    private $http;

    /** @var ReturnResolver */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new FakeHttpClient();
        $this->resolver = new ReturnResolver(new CarbonApi($this->http, 'https://api.carbon.ir', 'MRC'), 1200);

        Functions\when('WC')->justReturn(new class {
            public function payment_gateways()
            {
                return null;
            }
        });
    }

    private function order(array $props = []): WC_Order
    {
        return new WC_Order($props + [
            'meta' => [
                OrderMeta::TOKEN => 'tok-2',
                OrderMeta::TOKENS => ['tok-1', 'tok-2'],
                OrderMeta::TOKEN_CREATED_AT => self::NOW - 60,
                OrderMeta::REDIRECT_URL => 'https://cpg.carbon.ir/payments/tok-2',
            ],
        ]);
    }

    private function verifies(bool $paid): void
    {
        $this->http->respond(200, ['data' => ['token' => 'tok-2', 'invoice_amount' => 213960000, 'is_paid' => $paid]]);
    }

    public function testUnknownOrder(): void
    {
        self::assertSame(ReturnOutcome::UNKNOWN_ORDER, $this->resolver->resolve(null, 'success', 'tok-2', self::NOW));
    }

    public function testAlreadyPaidOrderIsNotVerifiedAgain(): void
    {
        $outcome = $this->resolver->resolve($this->order(['status' => 'processing']), 'success', 'tok-2', self::NOW);

        self::assertSame(ReturnOutcome::ALREADY_DONE, $outcome);
        self::assertSame([], $this->http->requests);
    }

    public function testForeignTokenIsRejectedWithoutCallingCarbon(): void
    {
        self::assertSame(ReturnOutcome::INVALID_TOKEN, $this->resolver->resolve($this->order(), 'success', 'someone-else', self::NOW));
        self::assertSame(ReturnOutcome::INVALID_TOKEN, $this->resolver->resolve($this->order(), 'success', '', self::NOW));
        self::assertSame([], $this->http->requests);
    }

    public function testVerifyDecidesEvenWhenCarbonSaysFailed(): void
    {
        // Backend issue #8: a bank-paid order comes back as status=failed but verify says paid.
        $this->verifies(true);

        self::assertSame(ReturnOutcome::PAID, $this->resolver->resolve($this->order(), 'failed', 'tok-2', self::NOW));
        self::assertSame('https://api.carbon.ir/v1/cpg/payments/verify', $this->http->requests[0]['url']);
        self::assertSame(
            ['merchant_code' => 'MRC', 'token' => 'tok-2', 'invoice_reference' => '1287', 'invoice_amount' => 213960000],
            $this->http->requests[0]['payload']
        );
    }

    public function testAnEarlierTokenOfTheSameOrderIsAccepted(): void
    {
        $this->verifies(true);

        self::assertSame(ReturnOutcome::PAID, $this->resolver->resolve($this->order(), 'success', 'tok-1', self::NOW));
    }

    public function testSuccessButNotYetVerifiedIsPending(): void
    {
        $this->verifies(false);

        self::assertSame(ReturnOutcome::PENDING, $this->resolver->resolve($this->order(), 'success', 'tok-2', self::NOW));
    }

    public function testFailedAndUnverifiedAndExpired(): void
    {
        $this->verifies(false);
        self::assertSame(ReturnOutcome::CANCELLED, $this->resolver->resolve($this->order(), 'failed', 'tok-2', self::NOW));

        $this->verifies(false);
        self::assertSame(ReturnOutcome::UNVERIFIED, $this->resolver->resolve($this->order(), '', 'tok-2', self::NOW));

        $this->verifies(false);
        self::assertSame(ReturnOutcome::EXPIRED, $this->resolver->resolve($this->order(), 'failed', 'tok-2', self::NOW + 1200));
    }

    public function testVerifyTransportErrorKeepsOrderPending(): void
    {
        $this->http->failNetwork();
        self::assertSame(ReturnOutcome::API_ERROR, $this->resolver->resolve($this->order(), 'success', 'tok-2', self::NOW));

        $this->http->respond(400, ['type' => 'payment_token_error', 'message' => 'x']);
        self::assertSame(ReturnOutcome::INVALID_TOKEN, $this->resolver->resolve($this->order(), 'success', 'tok-2', self::NOW));
    }

    public function testLegacyGsmpayTokenIsAccepted(): void
    {
        $this->verifies(true);
        $order = new WC_Order(['payment_method' => 'WC_GSMPay', 'meta' => [OrderMeta::LEGACY_TOKEN => 'gsm-tok']]);

        self::assertSame(ReturnOutcome::PAID, $this->resolver->resolve($order, 'success', 'gsm-tok', self::NOW));
    }

    public function testBrowserReturnOfPaidOrderCompletesPayment(): void
    {
        $this->verifies(true);
        $order = $this->order();
        Functions\when('wc_get_order')->justReturn($order);

        $response = (new ReturnHandler($this->resolver))->handle(
            ['wc_order' => '1287', 'key' => 'wc_order_abc123', 'status' => 'failed', 'token' => 'tok-2'],
            true,
            self::NOW
        );

        self::assertSame('processing', $order->status);
        self::assertSame('tok-2', $order->transaction_id);
        self::assertSame(self::NOW, $order->get_meta(OrderMeta::VERIFIED_AT));
        self::assertSame(OrderMeta::STATUS_PAID, $order->get_meta(OrderMeta::STATUS));
        self::assertSame('', $order->get_meta(OrderMeta::REDIRECT_URL));
        self::assertSame('redirect', $response->type);
        self::assertSame($order->get_checkout_order_received_url(), $response->url);
        self::assertSame([], $response->notices, 'The result card replaces the notice.');
    }

    public function testCancelledPaymentLeavesOrderFailedAndPayableNeverCancelled(): void
    {
        $this->verifies(false);
        $order = $this->order();
        Functions\when('wc_get_order')->justReturn($order);

        $response = (new ReturnHandler($this->resolver))->handle(
            ['wc_order' => '1287', 'status' => 'failed', 'token' => 'tok-2'],
            true,
            self::NOW
        );

        self::assertSame('failed', $order->status);
        self::assertTrue($order->needs_payment(), 'A late webhook or pay-again must still be able to complete it.');
        self::assertSame(['پرداخت در کربن لغو شد یا ناموفق بود.'], $order->status_notes);
        self::assertSame('', $order->get_meta(OrderMeta::REDIRECT_URL), 'Pay again must create a fresh token.');
        self::assertStringStartsWith($order->get_checkout_order_received_url(), $response->url);
        self::assertStringContainsString('carbon_result=cancelled', $response->url);
        self::assertStringContainsString('carbon_status=failed', $response->url);
        self::assertSame([], $response->notices, 'The result card replaces the notice.');
    }

    public function testRepeatedFailureDoesNotAddAnotherStatusNote(): void
    {
        $this->verifies(false);
        $order = $this->order(['status' => 'failed']);
        Functions\when('wc_get_order')->justReturn($order);

        (new ReturnHandler($this->resolver))->handle(['wc_order' => '1287', 'status' => 'failed', 'token' => 'tok-2'], true, self::NOW);

        self::assertSame([], $order->status_notes);
    }

    public function testPendingKeepsOrderStatus(): void
    {
        $this->verifies(false);
        $order = $this->order();
        Functions\when('wc_get_order')->justReturn($order);

        $response = (new ReturnHandler($this->resolver))->handle(['wc_order' => '1287', 'status' => 'success', 'token' => 'tok-2'], true, self::NOW);

        self::assertSame('pending', $order->status);
        self::assertStringContainsString('carbon_result=pending', $response->url);
        self::assertSame([], $response->notices, 'The result card replaces the notice.');
    }

    public function testLoggedInOwnerIsSentToViewOrder(): void
    {
        $this->verifies(false);
        $order = $this->order(['customer_id' => 42]);
        Functions\when('wc_get_order')->justReturn($order);
        Functions\when('get_current_user_id')->justReturn(42);

        $response = (new ReturnHandler($this->resolver))->handle(['wc_order' => '1287', 'status' => 'failed', 'token' => 'tok-2'], true, self::NOW);

        self::assertStringStartsWith('https://shop.test/my-account/view-order/1287/', $response->url);
    }

    public function testWebhookGetsJsonWithoutRedirectOrNotices(): void
    {
        $this->verifies(true);
        $order = $this->order();
        Functions\when('wc_get_order')->justReturn($order);

        $response = (new ReturnHandler($this->resolver))->handle(['wc_order' => '1287', 'status' => 'success', 'token' => 'tok-2'], false, self::NOW);

        self::assertSame('json', $response->type);
        self::assertSame(200, $response->status);
        self::assertSame(['outcome' => ReturnOutcome::PAID], $response->body);
        self::assertSame([], $response->notices);
        self::assertSame('processing', $order->status);
    }

    public function testWrongOrderKeyIsTreatedAsUnknownOrder(): void
    {
        $order = $this->order();
        Functions\when('wc_get_order')->justReturn($order);

        $response = (new ReturnHandler($this->resolver))->handle(['wc_order' => '1287', 'key' => 'nope', 'status' => 'success', 'token' => 'tok-2'], false, self::NOW);

        self::assertSame(404, $response->status);
        self::assertSame(['outcome' => ReturnOutcome::UNKNOWN_ORDER], $response->body);
        self::assertSame('pending', $order->status);
        self::assertSame([], $this->http->requests);
    }

    public function testMissingOrderInBrowserGoesBackToCheckout(): void
    {
        Functions\when('wc_get_order')->justReturn(false);

        $response = (new ReturnHandler($this->resolver))->handle(['wc_order' => '999999'], true, self::NOW);

        self::assertSame('https://shop.test/checkout/', $response->url);
        self::assertSame([['اطلاعات بازگشت از کربن معتبر نیست.', 'error']], $response->notices);
    }
}
