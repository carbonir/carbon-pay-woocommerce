<?php

namespace Carbon\Pay\Tests\Unit\Admin;

use Carbon\Pay\Admin\ConnectionCheck;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Tests\FakeHttpClient;
use Carbon\Pay\Tests\TestCase;

final class ConnectionCheckTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new FakeHttpClient();
    }

    private function probe(): array
    {
        return ConnectionCheck::probe(new CarbonApi($this->http, 'https://api.carbon.ir', 'MRC-104582'), 1700000000);
    }

    public function testUnknownTokenMeansMerchantCodeAndIpPassed(): void
    {
        $this->http->respond(422, ['type' => 'payment_token_error', 'message' => 'توکن نامعتبر است.']);

        $result = $this->probe();

        self::assertSame('success', $result['status']);
        self::assertSame(1700000000, $result['checked_at']);
        self::assertSame('https://api.carbon.ir/v1/cpg/payments/verify', $this->http->requests[0]['url']);
        self::assertSame(
            ['merchant_code' => 'MRC-104582', 'token' => ConnectionCheck::PROBE_TOKEN, 'invoice_reference' => 'connection-check', 'invoice_amount' => 0],
            $this->http->requests[0]['payload']
        );
    }

    /**
     * @dataProvider failures
     */
    public function testFailuresNameWhatToFix(int $status, array $body, string $tone, string $title): void
    {
        $this->http->respond($status, $body);

        $result = $this->probe();

        self::assertSame($tone, $result['status']);
        self::assertSame($title, $result['title']);
    }

    public function failures(): array
    {
        return [
            'merchant code' => [422, ['type' => 'merchant_code_error', 'message' => 'x'], 'error', 'کد پذیرنده پذیرفته نشد'],
            'ip whitelist' => [422, ['type' => 'ip_address_error', 'message' => 'x'], 'error', 'IP سرور ثبت نشده است'],
            'gateway inactive' => [422, ['type' => 'gateway_not_activated', 'message' => 'x'], 'error', 'درگاه فعال نیست'],
            'rate limited' => [429, ['type' => 'too_many_requests', 'message' => 'x'], 'warning', 'درخواست‌ها زیاد بود'],
            'server error' => [500, ['type' => 'server_error', 'message' => 'x'], 'warning', 'پاسخ کربن قابل بررسی نبود'],
            // A 404 without carbon-service's token error may be a wrong API address, not a passed check.
            'bare not found' => [404, ['type' => 'not_found', 'message' => 'x'], 'warning', 'پاسخ کربن قابل بررسی نبود'],
        ];
    }

    public function testNetworkFailureNamesTheApiAddress(): void
    {
        $this->http->failNetwork('cURL error 6: Could not resolve host');

        $result = $this->probe();

        self::assertSame('error', $result['status']);
        self::assertStringContainsString('https://api.carbon.ir', $result['text']);
        self::assertStringContainsString('Could not resolve host', $result['text']);
    }

    public function testLastResultOnlyForTheSavedCode(): void
    {
        $this->options[ConnectionCheck::OPTION] = ['merchant_code' => 'MRC-1', 'status' => 'success', 'title' => 't', 'text' => 'x', 'checked_at' => 5];

        self::assertSame(['status' => 'success', 'title' => 't', 'text' => 'x', 'checked_at' => 5], ConnectionCheck::lastResult('MRC-1'));
        self::assertNull(ConnectionCheck::lastResult('MRC-2'));
        self::assertNull(ConnectionCheck::lastResult(''));
    }
}
