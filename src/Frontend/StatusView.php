<?php

namespace Carbon\Pay\Frontend;

use Carbon\Pay\GatewayId;
use Carbon\Pay\Order\OrderMeta;
use Carbon\Pay\Payment\ReturnHandler;
use Carbon\Pay\Payment\ReturnOutcome;
use Carbon\Pay\Plugin;
use Carbon\Pay\Support\Format;
use Carbon\Pay\View\Template;
use WC_Order;

/**
 * Status block on the thank-you and view-order pages.
 */
final class StatusView
{
    public const APP_URL = 'https://my.carbon.ir';

    /** Seconds between automatic re-checks while Carbon is verifying. */
    public const REFRESH_SECONDS = 10;

    /** @var int */
    private $tokenTtl;

    public function __construct(int $tokenTtl)
    {
        $this->tokenTtl = $tokenTtl;
    }

    public function register(): void
    {
        add_action('woocommerce_thankyou_' . GatewayId::ID, [$this, 'renderThankYou'], 5);
        add_action('woocommerce_view_order', [$this, 'renderViewOrder'], 5);
    }

    /**
     * @param int $orderId
     */
    public function renderThankYou($orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }

        $outcome = $order->is_paid() ? ReturnOutcome::PAID : $this->outcomeFromQuery();
        if ($outcome !== null) {
            echo $this->render($order, $outcome); // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    /**
     * @param int $orderId
     */
    public function renderViewOrder($orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order || !GatewayId::owns($order) || $order->is_paid()) {
            return;
        }

        $outcome = $this->outcomeFromQuery();
        if ($outcome !== null) {
            echo $this->render($order, $outcome); // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    public function render(WC_Order $order, string $outcome): string
    {
        $variant = self::variant($outcome, (int) ceil($this->tokenTtl / 60));
        if ($variant === null) {
            return '';
        }

        Plugin::instance()->assets()->enqueueFrontend();

        $recheck = add_query_arg([
            'status' => $this->statusFromQuery($outcome),
            'token' => OrderMeta::token($order),
        ], Plugin::callbackUrl($order));
        $urls = [
            // The receipt step starts a fresh Carbon payment; the pay form lets the payer pick another method.
            'pay' => $order->get_checkout_payment_url(true),
            'other' => $order->get_checkout_payment_url(),
            'recheck' => $recheck,
            'app' => self::APP_URL,
        ];

        $actions = [];
        foreach ($variant['actions'] as $action) {
            $actions[] = $action + ['url' => $urls[$action['target']]];
        }

        if ($outcome === ReturnOutcome::PENDING || $outcome === ReturnOutcome::PAID) {
            wp_enqueue_script('carbon-pay-order-status');
        }

        return Template::render('order/status', [
            'tone' => $variant['tone'],
            'icon' => $variant['icon'],
            'title' => $variant['title'],
            'text' => $variant['text'],
            'journey' => $variant['journey'],
            'note' => $variant['note'],
            'reference' => $outcome === ReturnOutcome::PAID ? (string) ($order->get_transaction_id() ?: OrderMeta::token($order)) : '',
            'amount_label' => $outcome === ReturnOutcome::PAID ? 'مبلغ پرداخت‌شده' : 'مبلغ سفارش',
            'amount' => Format::number($order->get_total()),
            'unit' => html_entity_decode(get_woocommerce_currency_symbol($order->get_currency()), ENT_QUOTES, 'UTF-8'),
            'paid_badge' => $outcome === ReturnOutcome::PAID,
            'actions' => $actions,
            'refresh_url' => $outcome === ReturnOutcome::PENDING ? $recheck : '',
            'refresh_seconds' => self::REFRESH_SECONDS,
        ]);
    }

    /**
     * Copy, tone and actions for each outcome (designs 1f–1l). Every card answers, in order: what
     * happened → where it stopped (journey) → is my money safe (note) → amount and what now.
     *
     * Journey: [step 1, line, step 2, line, step 3]; steps are done|success|pending|warning|cancelled|
     * expired|error|broken|unknown|todo, lines solid|dashed|brand.
     *
     * @return array{tone: string, icon: string, title: string, text: string, journey: string[], note: array{tone: string, text: string}|null, actions: array}|null
     */
    public static function variant(string $outcome, int $ttlMinutes): ?array
    {
        $payAgain = ['label' => 'پرداخت دوباره با کربن', 'style' => 'primary', 'icon' => 'ph-arrow-clockwise', 'target' => 'pay'];
        $other = ['label' => 'روش پرداخت دیگر', 'style' => 'link', 'icon' => '', 'target' => 'other'];

        switch ($outcome) {
            case ReturnOutcome::PAID:
                return [
                    'tone' => 'success',
                    'icon' => 'ph-bold ph-check',
                    'title' => 'خرید شما با کربن ثبت شد',
                    'text' => 'جزئیات این خرید و اقساط آن (اگر با اعتبار پرداخت کرده‌اید) در اپ کربن در دسترس است.',
                    'journey' => ['done', 'solid', 'done', 'solid', 'success'],
                    'note' => null,
                    'actions' => [
                        ['label' => 'مشاهده در اپ کربن', 'style' => 'outline', 'icon' => 'ph-arrow-up-left', 'target' => 'app'],
                    ],
                ];
            case ReturnOutcome::PENDING:
                return [
                    'tone' => 'brand',
                    'icon' => 'spinner',
                    'title' => 'در انتظار تایید کربن',
                    'text' => 'پرداخت شما دریافت شده و کربن در حال تایید آن است.',
                    'journey' => ['done', 'solid', 'done', 'brand', 'pending'],
                    'note' => ['tone' => 'safe', 'text' => 'لازم نیست دوباره پرداخت کنید؛ این صفحه خودکار به‌روز می‌شود.'],
                    'actions' => [
                        ['label' => 'به‌روزرسانی', 'style' => 'outline', 'icon' => 'ph-arrows-clockwise', 'target' => 'recheck'],
                    ],
                ];
            case ReturnOutcome::API_ERROR:
                // Never invite a double charge: re-checking is the primary action, paying again is demoted.
                return [
                    'tone' => 'warning',
                    'icon' => 'ph ph-cloud-slash',
                    'title' => 'وضعیت پرداخت هنوز مشخص نیست',
                    'text' => 'ارتباط با کربن برقرار نشد و هنوز نمی‌دانیم پرداخت انجام شده یا نه.',
                    'journey' => ['done', 'solid', 'warning', 'dashed', 'todo'],
                    'note' => ['tone' => 'warning', 'text' => 'اگر پرداخت کرده‌اید، دوباره پرداخت نکنید؛ سفارش به‌طور خودکار تایید می‌شود.'],
                    'actions' => [
                        ['label' => 'پرداخت دوباره', 'style' => 'outline', 'icon' => '', 'target' => 'pay'],
                        ['label' => 'بررسی دوباره', 'style' => 'primary', 'icon' => 'ph-arrows-clockwise', 'target' => 'recheck'],
                    ],
                ];
            case ReturnOutcome::CANCELLED:
                return [
                    'tone' => 'neutral',
                    'icon' => 'ph-bold ph-x',
                    'title' => 'پرداخت را در کربن لغو کردید',
                    'text' => 'سفارش شما نگه داشته شده است. هر وقت آماده بودید، دوباره پرداخت کنید.',
                    'journey' => ['done', 'solid', 'cancelled', 'dashed', 'todo'],
                    'note' => ['tone' => 'safe', 'text' => 'مبلغی از حساب شما کسر نشده است.'],
                    'actions' => [$other, $payAgain],
                ];
            case ReturnOutcome::EXPIRED:
                return [
                    'tone' => 'neutral',
                    'icon' => 'ph ph-timer',
                    'title' => 'مهلت پرداخت تمام شد',
                    'text' => $ttlMinutes > 0
                        ? sprintf('لینک پرداخت فقط %s دقیقه معتبر است. با «پرداخت دوباره» لینک تازه‌ای ساخته می‌شود.', Format::persianDigits((string) $ttlMinutes))
                        : 'لینک پرداخت مدت محدودی معتبر است. با «پرداخت دوباره» لینک تازه‌ای ساخته می‌شود.',
                    'journey' => ['done', 'solid', 'expired', 'dashed', 'todo'],
                    'note' => ['tone' => 'safe', 'text' => 'پرداختی انجام نشده و مبلغی از حساب شما کسر نشده است.'],
                    'actions' => [$other, $payAgain],
                ];
            case ReturnOutcome::UNVERIFIED:
                return [
                    'tone' => 'error',
                    'icon' => 'ph-bold ph-exclamation-mark',
                    'title' => 'پرداخت تایید نشد',
                    'text' => 'کربن این پرداخت را تایید نکرد. می‌توانید دوباره پرداخت کنید.',
                    'journey' => ['done', 'solid', 'done', 'solid', 'error'],
                    'note' => ['tone' => 'safe', 'text' => 'اگر مبلغی از حساب شما کم شده باشد، کربن آن را برمی‌گرداند.'],
                    'actions' => [$other, $payAgain],
                ];
            case ReturnOutcome::INVALID_TOKEN:
                return [
                    'tone' => 'error',
                    'icon' => 'ph ph-link-break',
                    'title' => 'این پرداخت قابل شناسایی نبود',
                    'text' => 'اطلاعات بازگشت از کربن با این سفارش مطابقت نداشت. می‌توانید دوباره پرداخت کنید.',
                    'journey' => ['done', 'dashed', 'unknown', 'dashed', 'broken'],
                    'note' => ['tone' => 'safe', 'text' => 'مبلغی از این طریق ثبت نشده است.'],
                    'actions' => [$other, $payAgain],
                ];
            default:
                return null;
        }
    }

    private function outcomeFromQuery(): ?string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
        $outcome = isset($_GET[ReturnHandler::RESULT_ARG]) ? sanitize_key(wp_unslash($_GET[ReturnHandler::RESULT_ARG])) : '';

        return self::variant($outcome, 0) !== null && $outcome !== ReturnOutcome::PAID ? $outcome : null;
    }

    private function statusFromQuery(string $outcome): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
        $status = isset($_GET[ReturnHandler::STATUS_ARG]) ? sanitize_key(wp_unslash($_GET[ReturnHandler::STATUS_ARG])) : '';

        return $outcome === ReturnOutcome::PENDING ? 'success' : $status;
    }
}
