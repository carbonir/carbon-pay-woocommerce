<?php

namespace Carbon\Pay\Payment;

use Carbon\Pay\Api\ApiException;

/**
 * Turns local validation failures and Carbon API errors into the copy defined in
 * carbon-pay-ui (checkout-classic, checkout-blocks).
 */
final class ErrorPresenter
{
    public const GENERIC_TITLE = 'ارتباط با کربن برقرار نشد.';
    public const GENERIC_TEXT = 'لطفاً دوباره تلاش کنید.';

    private const SHAHKAR_DEFAULT = 'متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد.';
    private const INQUIRY_TITLE = 'سرویس استعلام در حال حاضر در دسترس نیست.';
    private const NAMES_TEXT = 'نام و نام خانوادگی فقط باید شامل حروف فارسی باشد.';

    public static function required(string $field): PayerError
    {
        $label = self::fieldLabel($field);

        return new PayerError(
            PayerError::CODE_REQUIRED,
            $field,
            'error',
            $label . ' یک گزینه الزامی است.',
            '',
            '<strong>' . $label . '</strong> یک گزینه الزامی است.'
        );
    }

    public static function invalid(string $field): PayerError
    {
        $label = self::fieldLabel($field);

        return new PayerError(
            PayerError::CODE_INVALID,
            $field,
            'error',
            $label . ' خود را صحیح وارد کنید.',
            '',
            '<strong>' . $label . '</strong> خود را صحیح وارد کنید.'
        );
    }

    public static function names(): PayerError
    {
        return new PayerError(
            PayerError::CODE_NAMES,
            PayerError::FIELD_NAMES,
            'error',
            self::NAMES_TEXT,
            '',
            '<strong>نام و نام خانوادگی</strong> فقط باید شامل حروف فارسی باشد.'
        );
    }

    public static function minAmount(): PayerError
    {
        return new PayerError(
            PayerError::CODE_MIN_AMOUNT,
            null,
            'error',
            'مبلغ سفارش کمتر از حداقل مجاز کربن است.',
            'برای این سفارش روش پرداخت دیگری انتخاب کنید.',
            'مبلغ سفارش کمتر از حداقل مجاز کربن است.'
        );
    }

    public static function generic(): PayerError
    {
        return new PayerError(
            PayerError::CODE_GATEWAY,
            null,
            'error',
            self::GENERIC_TITLE,
            self::GENERIC_TEXT,
            self::GENERIC_TITLE . ' ' . self::GENERIC_TEXT
        );
    }

    /**
     * Payer-facing error for a failed POST /v1/cpg/payments. Merchant-side problems
     * (merchant code, IP whitelist, inactive gateway) always collapse to the generic message.
     */
    public static function forPayer(ApiException $e): PayerError
    {
        if ($e->kind() !== ApiException::KIND_VALIDATION) {
            return self::generic();
        }

        // Shahkar inquiry: carbon-service reports both outcomes on `national_code`.
        $shahkar = $e->firstFieldError('national_code');
        if ($shahkar !== null) {
            if (strpos($shahkar, 'استعلام') !== false) {
                return new PayerError(
                    PayerError::CODE_INQUIRY,
                    null,
                    'warning',
                    self::INQUIRY_TITLE,
                    'چند دقیقه دیگر دوباره «پرداخت با کربن» را بزنید.',
                    self::INQUIRY_TITLE
                );
            }

            $message = $shahkar !== '' ? $shahkar : self::SHAHKAR_DEFAULT;

            return new PayerError(PayerError::CODE_SHAHKAR, PayerError::FIELD_NATIONAL_CODE, 'error', $message, '', $message);
        }

        if ($e->firstFieldError('invoice_amount') !== null) {
            return self::minAmount();
        }

        if ($e->firstFieldError('payer_first_name') !== null || $e->firstFieldError('payer_last_name') !== null) {
            return self::names();
        }

        if ($e->firstFieldError('payer_mobile') !== null) {
            return self::invalid(PayerError::FIELD_MOBILE);
        }

        if ($e->firstFieldError('payer_national_code') !== null) {
            return self::invalid(PayerError::FIELD_NATIONAL_CODE);
        }

        return self::generic();
    }

    private static function fieldLabel(string $field): string
    {
        return $field === PayerError::FIELD_MOBILE ? 'شماره موبایل' : 'کد ملی';
    }
}
