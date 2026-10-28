<?php
/**
 * «قدرت خرید شما در کربن» and its sources as chips (design 2a). Mirrored in assets/js/checkout-blocks.js.
 */
defined('ABSPATH') || exit;

$carbon_pay_sources = [
    ['ph-wallet', 'پول نقد'],
    ['ph-coins', 'طلا و نقره'],
    ['ph-currency-circle-dollar', 'رمزارز'],
    ['ph-hand-coins', 'اعتبار و تسهیلات'],
];
?>
<div class="cp-power">
    <span class="cp-power__label" id="cp-power-label">قدرت خرید شما در کربن</span>
    <ul class="cp-chips" aria-labelledby="cp-power-label">
        <?php foreach ($carbon_pay_sources as [$carbon_pay_icon, $carbon_pay_text]) : ?>
            <li class="cp-chip"><i class="ph <?php echo esc_attr($carbon_pay_icon); ?>" aria-hidden="true"></i><?php echo esc_html($carbon_pay_text); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
