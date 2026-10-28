<?php
/**
 * Carbon header card above the gateway settings (tier A). carbon-pay-ui: admin-gateway-settings.html.
 */
defined('ABSPATH') || exit;

// The final merchant-panel and docs URLs are not published yet (carbon-pay-ui CHANGES: "URLs TBD").
$carbon_pay_links = apply_filters('carbon_pay_settings_links', [
    'merchant_panel' => 'https://carbon.ir/fa',
    'docs' => 'https://carbon.ir/fa/contact-us',
]);
?>
<div class="carbon-pay cp-settings">
    <section class="cp-hero cp-header" aria-label="کربن">
        <img class="cp-hero__rock" src="<?php echo esc_url(CARBON_PAY_URL . 'assets/images/carbon-rock.webp'); ?>" alt="">
        <div class="cp-header__body">
            <span class="cp-tile cp-tile--ink"><span class="cp-mark" role="img" aria-label="کربن"></span></span>
            <div class="cp-header__text">
                <span class="cp-header__title">فروش بیشتر با قدرت خرید مشتریان کربن</span>
                <span class="cp-hero__muted">مشتریان با پول نقد، طلا و نقره یا اعتبار می‌خرند و شما آنی تسویه می‌گیرید.</span>
            </div>
            <div class="cp-header__links">
                <a class="cp-btn cp-btn--light" href="<?php echo esc_url($carbon_pay_links['merchant_panel']); ?>" target="_blank" rel="noopener">پنل پذیرندگی<i class="ph ph-arrow-up-left" aria-hidden="true"></i></a>
                <a class="cp-btn cp-btn--on-dark" href="<?php echo esc_url($carbon_pay_links['docs']); ?>" target="_blank" rel="noopener">راهنما<i class="ph ph-book-open" aria-hidden="true"></i></a>
            </div>
        </div>
    </section>
</div>
