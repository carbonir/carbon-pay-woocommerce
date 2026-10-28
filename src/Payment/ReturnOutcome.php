<?php

namespace Carbon\Pay\Payment;

/**
 * What happened when the payer (or Carbon's webhook) came back to the callback URL.
 */
final class ReturnOutcome
{
    /** Verify reported is_paid = true. */
    public const PAID = 'paid';
    /** Order was already paid or no longer needs payment. */
    public const ALREADY_DONE = 'already_done';
    /** ?status=success but Carbon has not verified yet (VERIFY_PENDING). Keep the order pending. */
    public const PENDING = 'pending';
    /** The payer cancelled or the payment failed on Carbon's side. Order → failed, still payable. */
    public const CANCELLED = 'cancelled';
    /** Carbon did not verify the payment. Order → failed, still payable. */
    public const UNVERIFIED = 'unverified';
    /** The token outlived its lifetime without being paid. Order → failed, pay again = new token. */
    public const EXPIRED = 'expired';
    /** The token does not belong to this order. Order unchanged. */
    public const INVALID_TOKEN = 'invalid_token';
    /** Verify could not be completed (network, 5xx). Order unchanged; the webhook will retry. */
    public const API_ERROR = 'api_error';
    /** No such order. */
    public const UNKNOWN_ORDER = 'unknown_order';

    public static function marksFailed(string $outcome): bool
    {
        return in_array($outcome, [self::CANCELLED, self::UNVERIFIED, self::EXPIRED], true);
    }
}
