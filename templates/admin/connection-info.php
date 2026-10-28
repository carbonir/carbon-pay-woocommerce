<?php
/**
 * Connection details the merchant registers with Carbon (tier A). carbon-pay-ui: admin-gateway-settings.html.
 *
 * @var string $callback_url
 * @var string $check_url
 * @var string $check_nonce
 * @var array{status: string, title: string, text: string, checked_at: int}|null $last_check
 */

use Carbon\Pay\Support\Format;

defined('ABSPATH') || exit;

$carbon_pay_check_icons = [
    'success' => 'ph-fill ph-check-circle',
    'warning' => 'ph-fill ph-warning',
    'error' => 'ph-fill ph-warning-circle',
];
?>
<div class="carbon-pay cp-settings">
    <section class="cp-panel" aria-labelledby="cp-info-h">
        <h3 class="cp-section-title" id="cp-info-h">اطلاعات اتصال برای ثبت در کربن</h3>
        <div class="cp-info__grid">
            <div class="cp-info__item">
                <div class="cp-row">
                    <span class="cp-tile"><i class="ph-duotone ph-arrow-u-down-left" aria-hidden="true"></i></span>
                    <span class="cp-row__text"><span class="cp-row__title" id="cp-cb-l">آدرس بازگشت (Callback URL)</span><span class="cp-row__sub">افزونه شماره سفارش را خودش به این آدرس اضافه می‌کند.</span></span>
                </div>
                <div class="cp-copy">
                    <span class="cp-copy__value" aria-labelledby="cp-cb-l"><?php echo esc_html($callback_url); ?></span>
                    <button type="button" class="cp-icon-btn" data-copy="<?php echo esc_attr($callback_url); ?>" aria-label="کپی آدرس بازگشت"><i class="ph ph-copy"></i></button>
                    <span class="cp-visually-hidden" aria-live="polite"></span>
                </div>
            </div>
        </div>
        <div class="cp-check" data-carbon-check data-url="<?php echo esc_url($check_url); ?>" data-nonce="<?php echo esc_attr($check_nonce); ?>">
            <div class="cp-check__head">
                <div class="cp-row">
                    <span class="cp-tile"><i class="ph ph-shield-check" aria-hidden="true"></i></span>
                    <span class="cp-row__text"><span class="cp-row__title">وضعیت اتصال به کربن</span><span class="cp-row__sub">کد پذیرنده و IP این سرور را با یک درخواست آزمایشی به کربن بررسی می‌کند. پرداختی ساخته نمی‌شود.</span></span>
                </div>
                <button type="button" class="cp-btn cp-btn--outline cp-btn--sm" data-carbon-check-run><i class="ph ph-arrows-clockwise" aria-hidden="true"></i><span>بررسی اتصال</span></button>
            </div>
            <div class="cp-check__result" aria-live="polite">
                <?php if ($last_check) : ?>
                    <div class="cp-alert cp-alert--<?php echo esc_attr($last_check['status']); ?>">
                        <i class="<?php echo esc_attr($carbon_pay_check_icons[$last_check['status']] ?? 'ph-fill ph-warning'); ?>" aria-hidden="true"></i>
                        <span class="cp-alert__body">
                            <span class="cp-alert__title"><?php echo esc_html($last_check['title']); ?></span>
                            <span class="cp-alert__text"><?php echo esc_html($last_check['text']); ?></span>
                            <span class="cp-check__time"><?php echo esc_html(sprintf('آخرین بررسی: %s پیش', Format::persianDigits(human_time_diff((int) $last_check['checked_at'])))); ?></span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
