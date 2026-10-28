<?php

namespace Carbon\Pay\Checkout;

use Carbon\Pay\Payment\ErrorPresenter;
use Carbon\Pay\Payment\PayerError;
use Carbon\Pay\Support\PersianText;
use Carbon\Pay\Support\Validation;

/**
 * The payer identity Carbon needs (mobile + national code) and the billing names,
 * normalised and validated the way carbon-service will validate them.
 */
final class PayerDetails
{
    /** Classic checkout inputs (payment_fields()). */
    public const FIELD_MOBILE = 'carbon_mobile';
    public const FIELD_NATIONAL_CODE = 'carbon_national_code';

    /** Block checkout additional fields (location: contact). */
    public const BLOCK_MOBILE = 'carbon/mobile';
    public const BLOCK_NATIONAL_CODE = 'carbon/national_code';

    /** @var string */
    public $mobile;

    /** @var string */
    public $nationalCode;

    /** @var string */
    public $firstName;

    /** @var string */
    public $lastName;

    public function __construct(string $mobile, string $nationalCode, string $firstName = '', string $lastName = '')
    {
        $this->mobile = PersianText::normalizeDigits($mobile);
        $this->nationalCode = PersianText::normalizeDigits($nationalCode);
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
    }

    /**
     * @return PayerError[] Empty when valid.
     */
    public function validate(bool $checkNames = true): array
    {
        $errors = [];

        if ($this->mobile === '') {
            $errors[] = ErrorPresenter::required(PayerError::FIELD_MOBILE);
        } elseif (!Validation::mobile($this->mobile)) {
            $errors[] = ErrorPresenter::invalid(PayerError::FIELD_MOBILE);
        }

        if ($this->nationalCode === '') {
            $errors[] = ErrorPresenter::required(PayerError::FIELD_NATIONAL_CODE);
        } elseif (!Validation::nationalCode($this->nationalCode)) {
            $errors[] = ErrorPresenter::invalid(PayerError::FIELD_NATIONAL_CODE);
        }

        if ($checkNames && (!Validation::personName($this->firstName) || !Validation::personName($this->lastName))) {
            $errors[] = ErrorPresenter::names();
        }

        return $errors;
    }

    /**
     * Picks the first non-empty value among the given sources.
     *
     * @param array<int, mixed> $candidates
     */
    public static function firstFilled(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return (string) $candidate;
            }
        }

        return '';
    }
}
