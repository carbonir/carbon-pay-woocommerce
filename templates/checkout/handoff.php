<?php
/**
 * Order-pay hand-off to the hosted Carbon page (design 1d). WooCommerce already prints the order
 * number, date, total and method above it, so the card only says where the payer is going, when,
 * and gives a button that always works.
 *
 * @var string $redirect_url Carbon hosted-page URL.
 * @var string $host         Its host name, for the payer to check.
 * @var string $order_number Persian-digit order number.
 * @var int    $delay        Seconds before the automatic redirect.
 */
defined('ABSPATH') || exit;

$carbon_pay_seconds = \Carbon\Pay\Support\Format::persianDigits((string) $delay);
?>
<section class="carbon-pay cp-card cp-handoff" aria-labelledby="cp-handoff-title" data-carbon-redirect="<?php echo esc_url($redirect_url); ?>" data-carbon-delay="<?php echo (int) $delay; ?>" style="--cp-delay:<?php echo (int) $delay; ?>s">
    <noscript><meta http-equiv="refresh" content="<?php echo (int) $delay; ?>;url=<?php echo esc_url($redirect_url); ?>"></noscript>
    <span class="cp-brand"><span class="cp-mark" aria-hidden="true"></span>کربن</span>
    <div class="cp-card__intro">
        <h2 class="cp-card__title" id="cp-handoff-title">در حال انتقال به کربن</h2>
        <p class="cp-card__text">پرداخت سفارش <b class="cp-num" dir="ltr">#<?php echo esc_html($order_number); ?></b> را در صفحه امن کربن کامل می‌کنید.</p>
    </div>
    <div class="cp-countdown">
        <div class="cp-progress" aria-hidden="true"><span class="cp-progress__bar"></span></div>
        <span class="cp-countdown__text">انتقال خودکار تا <b class="cp-num" data-carbon-seconds><?php echo esc_html($carbon_pay_seconds); ?></b> ثانیه دیگر</span>
    </div>
    <a class="cp-btn cp-btn--block" href="<?php echo esc_url($redirect_url); ?>">ادامه در کربن<i class="ph ph-arrow-left" aria-hidden="true"></i></a>
    <p class="cp-domain"><i class="ph-fill ph-lock-simple" aria-hidden="true"></i><span>نشانی صفحه پرداخت:</span><span class="cp-domain__host" dir="ltr"><?php echo esc_html($host); ?></span></p>
</section>
