<?php

namespace Carbon\Pay\Admin;

use Carbon\Pay\Api\ApiException;
use Carbon\Pay\Api\CarbonApi;
use Carbon\Pay\Plugin;
use Carbon\Pay\Settings;

/**
 * «بررسی اتصال» in the gateway settings: is the merchant code active at Carbon, and does Carbon accept
 * requests from this server?
 *
 * The CPG API has no status endpoint, so this calls POST /v1/cpg/payments/verify with a token no payment
 * has. carbon-service checks the merchant code (merchant_code_error), then the IP whitelist
 * (ip_address_error), then looks the token up (payment_token_error): reaching the token lookup means
 * both checks passed. Nothing is created or changed at Carbon.
 */
final class ConnectionCheck
{
    public const ACTION = 'carbon_pay_check_connection';

    /** Result of the last check of the saved merchant code, shown when the settings page loads. */
    public const OPTION = 'carbon_pay_connection_check';

    public const PROBE_TOKEN = '00000000-0000-0000-0000-000000000000';

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * Checks the merchant code typed in the settings form, which may not be saved yet.
     */
    public function handle(): void
    {
        check_ajax_referer(self::ACTION);
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(null, 403);
        }

        $code = isset($_POST['merchant_code']) ? trim(sanitize_text_field(wp_unslash($_POST['merchant_code']))) : '';
        $saved = Settings::load()->merchantCode();

        $result = $code === '' ? self::missingCode() : self::probe(Plugin::instance()->apiFor($code), time());
        if ($code !== '' && $code === $saved) {
            update_option(self::OPTION, ['merchant_code' => $code] + $result, false);
        } elseif ($code !== '' && $result['status'] === 'success') {
            $result['text'] .= ' این کد هنوز ذخیره نشده است؛ «ذخیره تغییرات» را بزنید.';
        }

        wp_send_json_success($result);
    }

    /**
     * @return array{status: string, title: string, text: string, checked_at: int}|null
     */
    public static function lastResult(string $merchantCode): ?array
    {
        $last = get_option(self::OPTION);
        if ($merchantCode === '' || !is_array($last) || ($last['merchant_code'] ?? null) !== $merchantCode) {
            return null;
        }
        unset($last['merchant_code']);

        return $last;
    }

    /**
     * @return array{status: string, title: string, text: string, checked_at: int}
     */
    public static function probe(CarbonApi $api, int $now): array
    {
        try {
            $api->verify(self::PROBE_TOKEN, 'connection-check', 0);

            return self::result('success', $now);
        } catch (ApiException $e) {
            // Only carbon-service's own "unknown token" error proves the earlier checks passed; a bare
            // 404 `not_found` may come from a wrong API address.
            if ($e->type() === 'payment_token_error') {
                return self::result('success', $now);
            }
            Plugin::logApiError('connection check', $e);

            return self::result($e->kind(), $now, $e);
        }
    }

    /**
     * @return array{status: string, title: string, text: string, checked_at: int}
     */
    public static function missingCode(): array
    {
        return [
            'status' => 'warning',
            'title' => 'کد پذیرنده وارد نشده است',
            'text' => 'کد پذیرنده را از پنل پذیرندگی کربن در فیلد بالا وارد کنید و دوباره بررسی کنید.',
            'checked_at' => 0,
        ];
    }

    /**
     * @return array{status: string, title: string, text: string, checked_at: int}
     */
    private static function result(string $kind, int $now, ?ApiException $e = null): array
    {
        switch ($kind) {
            case 'success':
                [$status, $title, $text] = ['success', 'اتصال برقرار است', 'کربن کد پذیرنده را شناخت و درخواست این سرور را پذیرفت. خریداران می‌توانند با کربن پرداخت کنند.'];
                break;
            case ApiException::KIND_MERCHANT:
                [$status, $title, $text] = ['error', 'کد پذیرنده پذیرفته نشد', 'این کد در کربن نیست یا درگاه آن فعال نیست. کد را با پنل پذیرندگی کربن مقایسه کنید.'];
                break;
            case ApiException::KIND_GATEWAY_INACTIVE:
                [$status, $title, $text] = ['error', 'درگاه فعال نیست', 'درگاه این کد پذیرنده در کربن فعال نشده است. با پشتیبانی کربن تماس بگیرید.'];
                break;
            case ApiException::KIND_IP:
                [$status, $title, $text] = ['error', 'IP سرور ثبت نشده است', 'کد پذیرنده درست است، اما کربن درخواست این سرور را رد کرد. IP خروجی سرور را در پنل پذیرندگی کربن ثبت کنید.'];
                break;
            case ApiException::KIND_NOT_ALLOWED:
                [$status, $title, $text] = ['error', 'کربن درخواست را رد کرد', 'کربن اجازه این درخواست را نداد. با پشتیبانی کربن تماس بگیرید.'];
                break;
            case ApiException::KIND_NETWORK:
                [$status, $title, $text] = ['error', 'اتصال به کربن برقرار نشد', sprintf('سرور به %s دسترسی ندارد (%s). فایروال، DNS یا پروکسی خروجی سرور را بررسی کنید.', Settings::apiBaseUrl(), $e ? $e->getMessage() : '')];
                break;
            case ApiException::KIND_RATE_LIMITED:
                [$status, $title, $text] = ['warning', 'درخواست‌ها زیاد بود', 'یک دقیقه دیگر دوباره بررسی کنید.'];
                break;
            default:
                [$status, $title, $text] = ['warning', 'پاسخ کربن قابل بررسی نبود', sprintf('کربن پاسخ غیرمنتظره داد (HTTP %d). کمی بعد دوباره بررسی کنید.', $e ? $e->httpStatus() : 0)];
        }

        return ['status' => $status, 'title' => $title, 'text' => $text, 'checked_at' => $now];
    }
}
