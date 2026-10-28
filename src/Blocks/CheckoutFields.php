<?php

namespace Carbon\Pay\Blocks;

use Carbon\Pay\Checkout\PayerDetails;
use Carbon\Pay\Support\PersianText;
use Carbon\Pay\Support\Validation;
use WP_Error;

/**
 * Mobile and national code as block-checkout additional fields (location: contact).
 *
 * They are registered as optional so they never block another payment method. When Carbon is the
 * chosen method, Gateway::process_payment() requires them and reports a missing value as an error.
 */
final class CheckoutFields
{
    public function register(): void
    {
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        woocommerce_register_additional_checkout_field([
            'id' => PayerDetails::BLOCK_MOBILE,
            'label' => 'موبایل',
            'optionalLabel' => 'موبایل (اختیاری)',
            'location' => 'contact',
            'type' => 'text',
            'required' => false,
            'show_in_order_confirmation' => true,
            'attributes' => [
                'autocomplete' => 'tel',
                'maxLength' => 11,
            ],
            'sanitize_callback' => [self::class, 'sanitize'],
            'validate_callback' => [self::class, 'validateMobile'],
        ]);

        woocommerce_register_additional_checkout_field([
            'id' => PayerDetails::BLOCK_NATIONAL_CODE,
            'label' => 'کد ملی',
            'optionalLabel' => 'کد ملی (اختیاری)',
            'location' => 'contact',
            'type' => 'text',
            'required' => false,
            'show_in_order_confirmation' => true,
            'attributes' => [
                'maxLength' => 10,
            ],
            'sanitize_callback' => [self::class, 'sanitize'],
            'validate_callback' => [self::class, 'validateNationalCode'],
        ]);
    }

    /**
     * @param mixed $value
     */
    public static function sanitize($value): string
    {
        return PersianText::normalizeDigits(sanitize_text_field((string) $value));
    }

    /**
     * @param mixed $value
     * @return WP_Error|null
     */
    public static function validateMobile($value)
    {
        $value = PersianText::normalizeDigits((string) $value);
        if ($value !== '' && !Validation::mobile($value)) {
            return new WP_Error('carbon_invalid_mobile', 'شماره موبایل خود را صحیح وارد کنید.');
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return WP_Error|null
     */
    public static function validateNationalCode($value)
    {
        $value = PersianText::normalizeDigits((string) $value);
        if ($value !== '' && !Validation::nationalCode($value)) {
            return new WP_Error('carbon_invalid_national_code', 'کد ملی خود را صحیح وارد کنید.');
        }

        return null;
    }
}
