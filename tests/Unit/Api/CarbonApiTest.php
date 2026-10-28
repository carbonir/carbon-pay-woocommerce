<?php

namespace Carbon\Pay\Tests\Unit\Api;

use Brain\Monkey\Filters;
use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Settings;
use Carbon\Pay\Tests\FakeHttpClient;
use Carbon\Pay\Tests\TestCase;

final class CarbonApiTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    /** @var CarbonApi */
    private $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new FakeHttpClient();
        $this->api = new CarbonApi($this->http, 'https://api.carbon.ir/', 'MRC-104582');
    }

    public function testCreatePaymentPostsWithMerchantCode(): void
    {
        $this->http->respond(200, ['data' => ['token' => 'tok-1', 'redirect_url' => 'https://cpg.carbon.ir/payments/tok-1']]);

        $result = $this->api->createPayment(['invoice_reference' => '1287', 'invoice_amount' => 213960000]);

        self::assertSame(['token' => 'tok-1', 'redirect_url' => 'https://cpg.carbon.ir/payments/tok-1'], $result);
        self::assertSame('https://api.carbon.ir/v1/cpg/payments', $this->http->requests[0]['url']);
        self::assertSame(
            ['merchant_code' => 'MRC-104582', 'invoice_reference' => '1287', 'invoice_amount' => 213960000],
            $this->http->requests[0]['payload']
        );
    }

    public function testVerifyIsPaidOnlyWhenStrictlyTrue(): void
    {
        $this->http->respond(200, ['data' => ['token' => 'tok-1', 'invoice_amount' => 213960000, 'is_paid' => true]]);
        $this->http->respond(200, ['data' => ['token' => 'tok-1', 'invoice_amount' => 213960000, 'is_paid' => 1]]);

        self::assertTrue($this->api->verify('tok-1', '1287', 213960000)['is_paid']);
        self::assertFalse($this->api->verify('tok-1', '1287', 213960000)['is_paid']);
        self::assertSame(
            ['merchant_code' => 'MRC-104582', 'token' => 'tok-1', 'invoice_reference' => '1287', 'invoice_amount' => 213960000],
            $this->http->requests[0]['payload']
        );
        self::assertSame('https://api.carbon.ir/v1/cpg/payments/verify', $this->http->requests[0]['url']);
    }

    public function testErrorResponseThrowsClassifiedException(): void
    {
        $this->http->respond(400, ['type' => 'merchant_code_error', 'message' => 'کد پذیرنده نامعتبر است.']);

        try {
            $this->api->createPayment([]);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(ApiException::KIND_MERCHANT, $e->kind());
            self::assertSame('کد پذیرنده نامعتبر است.', $e->getMessage());
        }
    }

    public function testSuccessWithoutDataEnvelopeIsMalformed(): void
    {
        $this->http->respond(200, ['token' => 'tok-1']);

        $this->expectException(ApiException::class);
        $this->api->createPayment([]);
    }

    public function testMissingKeyIsMalformed(): void
    {
        $this->http->respond(200, ['data' => ['token' => 'tok-1']]);

        try {
            $this->api->createPayment([]);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(ApiException::KIND_MALFORMED, $e->kind());
        }
    }

    public function testNetworkFailurePropagates(): void
    {
        $this->http->failNetwork();

        try {
            $this->api->verify('tok-1', '1287', 1);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(ApiException::KIND_NETWORK, $e->kind());
        }
    }

    public function testApiBaseIsProduction(): void
    {
        self::assertSame('https://api.carbon.ir', Settings::apiBaseUrl());
    }

    public function testBaseUrlFilter(): void
    {
        Filters\expectApplied('carbon_pay_api_base')
            ->once()
            ->with('https://api.carbon.ir')
            ->andReturn('https://stage-api.carbon.ir/');

        self::assertSame('https://stage-api.carbon.ir', Settings::apiBaseUrl());
    }
}
