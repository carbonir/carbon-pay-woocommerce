<?php

namespace Carbon\Pay\Payment;

/**
 * An error shown to the payer at checkout. Either attached to one of the plugin's
 * fields (inline under the input) or shown as an alert inside the Carbon fieldset.
 */
final class PayerError
{
    public const FIELD_MOBILE = 'mobile';
    public const FIELD_NATIONAL_CODE = 'national_code';
    public const FIELD_NAMES = 'names';

    public const CODE_REQUIRED = 'required';
    public const CODE_INVALID = 'invalid';
    public const CODE_SHAHKAR = 'shahkar';
    public const CODE_INQUIRY = 'inquiry';
    public const CODE_MIN_AMOUNT = 'min_amount';
    public const CODE_NAMES = 'names';
    public const CODE_GATEWAY = 'gateway';

    /** @var string */
    public $code;

    /** @var string|null One of the FIELD_* constants, or null for a fieldset alert. */
    public $field;

    /** @var string error|warning */
    public $level;

    /** @var string */
    public $title;

    /** @var string */
    public $text;

    /** @var string Single-line HTML for wc_add_notice(). */
    public $notice;

    public function __construct(string $code, ?string $field, string $level, string $title, string $text, string $notice)
    {
        $this->code = $code;
        $this->field = $field;
        $this->level = $level;
        $this->title = $title;
        $this->text = $text;
        $this->notice = $notice;
    }

    public function isFieldError(): bool
    {
        return $this->field !== null && $this->field !== self::FIELD_NAMES;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): ?self
    {
        if (!isset($data['code'], $data['level'], $data['title'], $data['text'], $data['notice'])) {
            return null;
        }

        return new self(
            (string) $data['code'],
            isset($data['field']) ? (string) $data['field'] : null,
            (string) $data['level'],
            (string) $data['title'],
            (string) $data['text'],
            (string) $data['notice']
        );
    }
}
