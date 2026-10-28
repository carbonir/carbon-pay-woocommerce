<?php

namespace Carbon\Pay\Tests\Unit\Support;

use Carbon\Pay\Support\Format;
use Carbon\Pay\Support\Money;
use Carbon\Pay\Support\PersianText;
use Carbon\Pay\Support\Validation;
use Carbon\Pay\Tests\TestCase;

final class SupportTest extends TestCase
{
    public function testConvertsPersianAndArabicIndicDigits(): void
    {
        self::assertSame('09121234567', PersianText::toEnglishDigits('۰۹۱۲۱۲۳۴۵۶۷'));
        // Arabic-Indic zero (U+0660) was missing in the GSMPay plugin.
        self::assertSame('0012345679', PersianText::toEnglishDigits('٠٠١٢٣٤٥٦٧٩'));
    }

    public function testNormalizeDigitsStripsSpacesDashesAndZwnj(): void
    {
        self::assertSame('09121234567', PersianText::normalizeDigits(' ۰۹۱۲ ۱۲۳-۴۵۶۷ '));
        self::assertSame('0012345679', PersianText::normalizeDigits("۰۰۱۲\u{200c}۳۴۵۶۷۹"));
    }

    public function testNonScalarInputBecomesEmptyString(): void
    {
        self::assertSame('', PersianText::toEnglishDigits(['x']));
        self::assertSame('', PersianText::toEnglishDigits(true));
        self::assertSame('', PersianText::toEnglishDigits(null));
    }

    /**
     * @dataProvider mobiles
     */
    public function testMobile(string $mobile, bool $valid): void
    {
        self::assertSame($valid, Validation::mobile($mobile));
    }

    public function mobiles(): array
    {
        return [
            'valid' => ['09121234567', true],
            'too short' => ['0912123456', false],
            'no leading zero' => ['9121234567', false],
            'country code' => ['+989121234567', false],
            'letters' => ['0912abc4567', false],
        ];
    }

    /**
     * @dataProvider nationalCodes
     */
    public function testNationalCode(string $code, bool $valid): void
    {
        self::assertSame($valid, Validation::nationalCode($code));
    }

    public function nationalCodes(): array
    {
        return [
            'valid (remainder ≥ 2)' => ['0012345679', true],
            'valid (remainder < 2)' => ['0100000010', true],
            'bad checksum' => ['0012345678', false],
            'all digits equal' => ['1111111111', false],
            'nine digits' => ['001234567', false],
            'non digits' => ['00123456a9', false],
        ];
    }

    /**
     * Mirrors carbon-core ATHK\Core\Rules\Alphabet, used by carbon-service for payer names.
     *
     * @dataProvider names
     */
    public function testPersonName(string $name, bool $valid): void
    {
        self::assertSame($valid, Validation::personName($name));
    }

    public function names(): array
    {
        return [
            'persian' => ['علی', true],
            'with zwnj' => ["محمدعلی\u{200c}زاده", true],
            'with space' => ['سید علی', true],
            'persian digit (accepted by the backend rule)' => ['علی۲', true],
            'latin' => ['Ali', false],
            'mixed' => ['علی Rezaei', false],
            'empty' => ['', false],
            'spaces only' => ['   ', false],
        ];
    }

    public function testMoneyConvertsStoreCurrencyToRial(): void
    {
        self::assertSame(213960000, Money::toRial('IRT', 21396000));
        self::assertSame(1000, Money::toRial('IRR', 1000));
        self::assertSame(1500, Money::toRial('IRHR', 15));
        self::assertSame(2000, Money::toRial('irht', '2'));
    }

    public function testMoneyRoundsInsteadOfTruncating(): void
    {
        // 19.99 * 10 = 199.89999… which (int) used to truncate to 199.
        self::assertSame(200, Money::toRial('IRT', 19.99));
    }

    public function testMoneySupportedCurrencies(): void
    {
        self::assertTrue(Money::isSupported('IRT'));
        self::assertTrue(Money::isSupported('irr'));
        self::assertFalse(Money::isSupported('USD'));
        self::assertSame(2139600.0, Money::fromRial('IRT', 21396000));
    }

    public function testFormatUsesPersianDigitsAndArabicThousandsSeparator(): void
    {
        self::assertSame('۲۱٬۳۹۶٬۰۰۰', Format::number(21396000));
        self::assertSame('۰', Format::number(0));
        self::assertSame('۲۱٬۳۹۶٬۰۰۰ تومان', Format::money(21396000.4, 'تومان'));
        self::assertSame('۱۲۸۷', Format::persianDigits('1287'));
    }

    public function testShortToken(): void
    {
        self::assertSame('9f3c1a7e-…4a19', Format::shortToken('9f3c1a7e-52b0-4d1e-8a77-1c02be6f4a19'));
        self::assertSame('short', Format::shortToken('short'));
    }
}
