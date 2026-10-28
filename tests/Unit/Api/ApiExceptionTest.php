<?php

namespace Carbon\Pay\Tests\Unit\Api;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\Response;
use Carbon\Pay\Tests\TestCase;

final class ApiExceptionTest extends TestCase
{
    /**
     * Error types thrown by carbon-service (Gateway/Orders exceptions, Shared/Exceptions/Handler).
     *
     * @dataProvider types
     */
    public function testMapsCarbonErrorTypes(int $status, string $type, string $kind): void
    {
        $e = ApiException::fromResponse(new Response($status, (string) json_encode(['type' => $type, 'message' => 'x'])));

        self::assertSame($kind, $e->kind());
        self::assertSame($type, $e->type());
        self::assertSame($status, $e->httpStatus());
    }

    public function types(): array
    {
        return [
            [422, 'validation_error', ApiException::KIND_VALIDATION],
            [400, 'merchant_code_error', ApiException::KIND_MERCHANT],
            [400, 'ip_address_error', ApiException::KIND_IP],
            [400, 'gateway_not_activated', ApiException::KIND_GATEWAY_INACTIVE],
            [403, 'authorization_error', ApiException::KIND_NOT_ALLOWED],
            [400, 'invalid_order_status', ApiException::KIND_INVALID_STATUS],
            [400, 'payment_token_error', ApiException::KIND_TOKEN],
            [404, 'not_found', ApiException::KIND_TOKEN],
            [400, 'invoice_amount_error', ApiException::KIND_AMOUNT_MISMATCH],
            [429, 'too_many_requests', ApiException::KIND_RATE_LIMITED],
            [503, 'service_unavailable', ApiException::KIND_SERVER],
            [500, 'server_error', ApiException::KIND_SERVER],
        ];
    }

    public function testValidationErrorsAreKeptPerField(): void
    {
        $e = ApiException::fromResponse(new Response(422, (string) json_encode([
            'type' => 'validation_error',
            'errors' => [
                'national_code' => ['متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد.'],
                'payer_first_name' => ['a', 'b'],
            ],
        ], JSON_UNESCAPED_UNICODE)));

        self::assertSame('متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد.', $e->firstFieldError('national_code'));
        self::assertSame(['a', 'b'], $e->fieldErrors()['payer_first_name']);
        self::assertNull($e->firstFieldError('payer_mobile'));
        self::assertSame('متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد. - a - b', $e->getMessage());
    }

    public function testUntyped403IsNotAllowed(): void
    {
        $e = ApiException::fromResponse(new Response(403, '<html>Forbidden</html>'));

        self::assertSame(ApiException::KIND_NOT_ALLOWED, $e->kind());
        self::assertTrue($e->isMerchantConfiguration());
    }

    public function testNonJson5xxIsServerError(): void
    {
        $e = ApiException::fromResponse(new Response(502, 'Bad Gateway'));

        self::assertSame(ApiException::KIND_SERVER, $e->kind());
        self::assertSame('Carbon API error (HTTP 502).', $e->getMessage());
        self::assertFalse($e->isMerchantConfiguration());
    }

    public function testMerchantConfigurationKinds(): void
    {
        self::assertTrue((new ApiException(ApiException::KIND_MERCHANT, ''))->isMerchantConfiguration());
        self::assertTrue((new ApiException(ApiException::KIND_IP, ''))->isMerchantConfiguration());
        self::assertFalse((new ApiException(ApiException::KIND_VALIDATION, ''))->isMerchantConfiguration());
        self::assertFalse(ApiException::network('timeout')->isMerchantConfiguration());
    }
}
