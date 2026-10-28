<?php

namespace Carbon\Pay\Tests\Unit\Payment;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Checkout\PayerDetails;
use Carbon\Pay\Payment\ErrorPresenter;
use Carbon\Pay\Payment\PayerError;
use Carbon\Pay\Tests\TestCase;

final class ErrorPresenterTest extends TestCase
{
    private function validation(array $errors): ApiException
    {
        return new ApiException(ApiException::KIND_VALIDATION, 'invalid', 'validation_error', 422, $errors);
    }

    public function testShahkarMismatchIsAFieldErrorOnNationalCode(): void
    {
        $error = ErrorPresenter::forPayer($this->validation([
            'national_code' => ['متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد.'],
        ]));

        self::assertSame(PayerError::CODE_SHAHKAR, $error->code);
        self::assertSame(PayerError::FIELD_NATIONAL_CODE, $error->field);
        self::assertTrue($error->isFieldError());
        self::assertSame('متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد.', $error->title);
    }

    public function testInquiryOutageIsAFieldsetWarning(): void
    {
        $error = ErrorPresenter::forPayer($this->validation([
            'national_code' => ['سرویس استعلام در حال حاضر در دسترس نیست.'],
        ]));

        self::assertSame(PayerError::CODE_INQUIRY, $error->code);
        self::assertNull($error->field);
        self::assertSame('warning', $error->level);
        self::assertSame('چند دقیقه دیگر دوباره «پرداخت با کربن» را بزنید.', $error->text);
    }

    public function testInvoiceAmountBelowMinimum(): void
    {
        $error = ErrorPresenter::forPayer($this->validation(['invoice_amount' => ['The invoice amount must be at least 10000.']]));

        self::assertSame(PayerError::CODE_MIN_AMOUNT, $error->code);
        self::assertSame('مبلغ سفارش کمتر از حداقل مجاز کربن است.', $error->title);
    }

    public function testNamesAndPayerFields(): void
    {
        self::assertSame(PayerError::CODE_NAMES, ErrorPresenter::forPayer($this->validation(['payer_last_name' => ['x']]))->code);

        $mobile = ErrorPresenter::forPayer($this->validation(['payer_mobile' => ['x']]));
        self::assertSame(PayerError::FIELD_MOBILE, $mobile->field);
        self::assertSame(PayerError::CODE_INVALID, $mobile->code);

        $code = ErrorPresenter::forPayer($this->validation(['payer_national_code' => ['x']]));
        self::assertSame(PayerError::FIELD_NATIONAL_CODE, $code->field);
    }

    /**
     * @dataProvider merchantSide
     */
    public function testMerchantSideAndTransportErrorsStayGeneric(ApiException $e): void
    {
        $error = ErrorPresenter::forPayer($e);

        self::assertSame(PayerError::CODE_GATEWAY, $error->code);
        self::assertSame('ارتباط با کربن برقرار نشد. لطفاً دوباره تلاش کنید.', $error->notice);
        self::assertStringNotContainsString('پذیرنده', $error->notice);
        self::assertStringNotContainsString('IP', $error->notice);
    }

    public function merchantSide(): array
    {
        return [
            'merchant code' => [new ApiException(ApiException::KIND_MERCHANT, 'کد پذیرنده نامعتبر است.', 'merchant_code_error', 400)],
            'ip whitelist' => [new ApiException(ApiException::KIND_IP, 'آدرس IP نامعتبر است.', 'ip_address_error', 400)],
            'network' => [ApiException::network('timeout')],
            'server' => [new ApiException(ApiException::KIND_SERVER, 'boom', 'server_error', 500)],
            'unknown validation field' => [new ApiException(ApiException::KIND_VALIDATION, 'x', 'validation_error', 422, ['items.0.name' => ['x']])],
        ];
    }

    public function testPayerDetailsValidation(): void
    {
        $empty = (new PayerDetails('', '', 'علی', 'رضایی'))->validate();
        self::assertSame([PayerError::CODE_REQUIRED, PayerError::CODE_REQUIRED], array_column(array_map(static function ($e) {
            return $e->toArray();
        }, $empty), 'code'));
        self::assertSame('<strong>شماره موبایل</strong> یک گزینه الزامی است.', $empty[0]->notice);
        self::assertSame('کد ملی یک گزینه الزامی است.', $empty[1]->title);

        $invalid = (new PayerDetails('912123', '1111111111', 'Ali', 'رضایی'))->validate();
        self::assertCount(3, $invalid);
        self::assertSame(PayerError::CODE_INVALID, $invalid[0]->code);
        self::assertSame(PayerError::CODE_INVALID, $invalid[1]->code);
        self::assertSame(PayerError::CODE_NAMES, $invalid[2]->code);
        self::assertFalse($invalid[2]->isFieldError(), 'Name errors belong to the theme billing fields.');

        $persianDigits = new PayerDetails('۰۹۱۲۱۲۳۴۵۶۷', '۰۰۱۲۳۴۵۶۷۹', 'علی', 'رضایی');
        self::assertSame([], $persianDigits->validate());
        self::assertSame('09121234567', $persianDigits->mobile);
        self::assertSame('0012345679', $persianDigits->nationalCode);

        self::assertSame([], (new PayerDetails('09121234567', '0012345679', 'Ali', 'Rezaei'))->validate(false));
    }

    public function testPayerErrorRoundTripsThroughSession(): void
    {
        $error = ErrorPresenter::invalid(PayerError::FIELD_MOBILE);

        self::assertEquals($error, PayerError::fromArray($error->toArray()));
        self::assertNull(PayerError::fromArray(['code' => 'x']));
    }

    public function testFirstFilled(): void
    {
        self::assertSame('b', PayerDetails::firstFilled([null, '', '  ', 'b', 'c']));
        self::assertSame('', PayerDetails::firstFilled([null, false === true ? 'x' : '']));
    }
}
