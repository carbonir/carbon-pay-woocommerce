<?php
/**
 * «پرداخت کربن» side metabox body (tier A). carbon-pay-ui: admin-order-edit.html.
 *
 * @var string $status           paid|refund|pending (refund: orders refunded through Carbon by pre-1.0 builds)
 * @var string $amount_rial      Persian-digit Rial amount sent to Carbon.
 * @var string $verified_at      Localised date or ''.
 * @var string $token
 * @var string $token_short
 * @var bool   $missing_merchant
 * @var string $settings_url
 */
defined('ABSPATH') || exit;

$carbon_pay_badges = [
    'paid' => ['success', 'ph-check-circle', 'پرداخت شد'],
    'refund' => ['brand', 'ph-arrow-u-up-left', 'لغو و برگشت مبلغ'],
    'pending' => ['warning', 'ph-clock', 'در انتظار پرداخت'],
];
$carbon_pay_badge = $carbon_pay_badges[$status] ?? $carbon_pay_badges['pending'];
?>
<div class="carbon-pay cp-metabox">
    <div class="cp-row">
        <span class="cp-tile cp-tile--ink cp-tile--sm"><span class="cp-mark" aria-hidden="true"></span></span>
        <span class="cp-row__text"><span class="cp-row__title cp-row__title--bold" style="font-size:14px">کربن</span><span class="cp-row__sub">پرداخت با قدرت خرید</span></span>
        <span class="cp-badge cp-badge--<?php echo esc_attr($carbon_pay_badge[0]); ?>"><i class="ph-fill <?php echo esc_attr($carbon_pay_badge[1]); ?>" aria-hidden="true"></i><?php echo esc_html($carbon_pay_badge[2]); ?></span>
    </div>
    <div class="cp-metabox__amount">
        <span class="cp-muted" style="font-size:12px">مبلغ ارسالی به کربن</span>
        <span class="cp-money cp-money--lg"><span class="cp-money__num"><?php echo esc_html($amount_rial); ?></span><span class="cp-money__unit">ریال</span></span>
    </div>
    <dl class="cp-kv">
        <?php if ($verified_at !== '') : ?>
            <div><dt>زمان تایید</dt><dd><?php echo esc_html($verified_at); ?></dd></div>
        <?php endif; ?>
        <?php if ($token !== '') : ?>
            <div><dt>شناسه پرداخت</dt><dd>
                <span class="cp-copy cp-copy--inline">
                    <span class="cp-copy__value" title="<?php echo esc_attr($token); ?>"><?php echo esc_html($token_short); ?></span>
                    <button type="button" class="cp-icon-btn" data-copy="<?php echo esc_attr($token); ?>" aria-label="کپی شناسه پرداخت"><i class="ph ph-copy"></i></button>
                    <span class="cp-visually-hidden" aria-live="polite"></span>
                </span>
            </dd></div>
        <?php endif; ?>
    </dl>
    <?php if ($missing_merchant) : ?>
        <div class="cp-alert cp-alert--warning"><i class="ph-fill ph-warning" aria-hidden="true"></i><div class="cp-alert__body"><span class="cp-alert__text">کد پذیرنده تنظیم نشده است. <a class="cp-link" href="<?php echo esc_url($settings_url); ?>">تنظیمات کربن</a></span></div></div>
    <?php endif; ?>
</div>
