<?php
/**
 * Classic checkout payment content (design 2a): purchasing-power chips, the payer fields Carbon
 * needs (block checkout collects them as contact fields instead), and the secure-page note.
 *
 * @var string                            $gateway_id
 * @var string                            $mobile
 * @var string                            $national_code
 * @var \Carbon\Pay\Payment\PayerError[]  $errors
 */

use Carbon\Pay\Payment\PayerError;
use Carbon\Pay\View\Template;

defined('ABSPATH') || exit;

$carbon_pay_field_errors = [];
$carbon_pay_alerts = [];
foreach ($errors as $carbon_pay_error) {
    if ($carbon_pay_error->isFieldError()) {
        $carbon_pay_field_errors[$carbon_pay_error->field] = $carbon_pay_field_errors[$carbon_pay_error->field] ?? $carbon_pay_error->title;
    } elseif ($carbon_pay_error->field === null) {
        $carbon_pay_alerts[] = $carbon_pay_error;
    }
}

$carbon_pay_fields = [
    [
        'key' => PayerError::FIELD_MOBILE,
        'id' => 'carbon_mobile',
        'label' => 'موبایل',
        'icon' => 'ph-device-mobile',
        'type' => 'tel',
        'autocomplete' => 'tel',
        'maxlength' => 11,
        'placeholder' => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
        'value' => $mobile,
    ],
    [
        'key' => PayerError::FIELD_NATIONAL_CODE,
        'id' => 'carbon_national_code',
        'label' => 'کد ملی',
        'icon' => 'ph-identification-card',
        'type' => 'text',
        'autocomplete' => 'off',
        'maxlength' => 10,
        'placeholder' => '۰۰۱۲۳۴۵۶۷۸',
        'value' => $national_code,
    ],
];
?>
<fieldset class="carbon-pay cp-checkout" id="wc-<?php echo esc_attr($gateway_id); ?>-cc-form">
    <legend class="cp-visually-hidden">پرداخت با کربن</legend>

    <?php echo Template::render('partials/power-sources'); // phpcs:ignore WordPress.Security.EscapeOutput ?>

    <div aria-live="polite">
        <?php foreach ($carbon_pay_alerts as $carbon_pay_alert) : ?>
            <div class="cp-alert cp-alert--<?php echo $carbon_pay_alert->level === 'warning' ? 'warning' : 'error'; ?>" role="alert">
                <i class="ph-fill <?php echo $carbon_pay_alert->level === 'warning' ? 'ph-warning' : 'ph-warning-circle'; ?>" aria-hidden="true"></i>
                <div class="cp-alert__body">
                    <span class="cp-alert__title"><?php echo esc_html($carbon_pay_alert->title); ?></span>
                    <?php if ($carbon_pay_alert->text !== '') : ?>
                        <span class="cp-alert__text"><?php echo esc_html($carbon_pay_alert->text); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="cp-fields">
        <?php foreach ($carbon_pay_fields as $carbon_pay_field) :
            $carbon_pay_error_text = $carbon_pay_field_errors[$carbon_pay_field['key']] ?? '';
            $carbon_pay_error_id = $carbon_pay_field['id'] . '_err';
            ?>
            <div class="cp-field<?php echo $carbon_pay_error_text !== '' ? ' is-invalid' : ''; ?>">
                <label class="cp-label" for="<?php echo esc_attr($carbon_pay_field['id']); ?>"><?php echo esc_html($carbon_pay_field['label']); ?><span class="cp-req" aria-hidden="true">*</span></label>
                <div class="cp-control">
                    <i class="ph <?php echo esc_attr($carbon_pay_field['icon']); ?>" aria-hidden="true"></i>
                    <input class="cp-input"
                        id="<?php echo esc_attr($carbon_pay_field['id']); ?>"
                        name="<?php echo esc_attr($carbon_pay_field['id']); ?>"
                        type="<?php echo esc_attr($carbon_pay_field['type']); ?>"
                        inputmode="numeric"
                        dir="ltr"
                        autocomplete="<?php echo esc_attr($carbon_pay_field['autocomplete']); ?>"
                        maxlength="<?php echo (int) $carbon_pay_field['maxlength']; ?>"
                        placeholder="<?php echo esc_attr($carbon_pay_field['placeholder']); ?>"
                        value="<?php echo esc_attr($carbon_pay_field['value']); ?>"
                        required
                        <?php echo $carbon_pay_error_text !== '' ? 'aria-invalid="true"' : ''; ?>
                        aria-describedby="<?php echo esc_attr($carbon_pay_error_id); ?>">
                    <i class="ph-fill ph-warning-circle cp-control__end" aria-hidden="true"></i>
                </div>
                <p class="cp-error" id="<?php echo esc_attr($carbon_pay_error_id); ?>" aria-live="polite"><?php if ($carbon_pay_error_text !== '') : ?><i class="ph-fill ph-warning-circle" aria-hidden="true"></i><?php echo esc_html($carbon_pay_error_text); ?><?php endif; ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="cp-secure-note"><i class="ph-fill ph-lock-simple" aria-hidden="true"></i><span>پس از ثبت سفارش، پرداخت را در صفحه امن کربن کامل می‌کنید.</span></p>
</fieldset>
