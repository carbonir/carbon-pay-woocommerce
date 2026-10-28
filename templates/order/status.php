<?php
/**
 * Carbon result card on thank-you / view-order (designs 1f–1l). Same anatomy for every outcome:
 * tone-coded icon → what happened → journey → reference or money note → amount and actions.
 *
 * @var string      $tone            success|brand|warning|neutral|error
 * @var string      $icon            Phosphor classes, or "spinner".
 * @var string      $title
 * @var string      $text
 * @var string[]    $journey         [step, line, step, line, step], see StatusView::variant().
 * @var array|null  $note            ['tone' => safe|warning, 'text' => …]
 * @var string      $reference       Carbon payment token (paid only), or ''.
 * @var string      $amount_label
 * @var string      $amount          Persian-digit amount.
 * @var string      $unit
 * @var bool        $paid_badge
 * @var array       $actions         [['label', 'style' => primary|outline|link, 'icon', 'url'], …]
 * @var string      $refresh_url     Automatic re-check target while verifying, or ''.
 * @var int         $refresh_seconds
 */

use Carbon\Pay\Support\Format;

defined('ABSPATH') || exit;

$carbon_pay_heading_id = 'cp-status-' . wp_unique_id();
$carbon_pay_labels = ['ثبت سفارش', 'پرداخت در کربن', 'تایید پرداخت'];
$carbon_pay_step_icons = [
    'done' => 'ph-bold ph-check',
    'success' => 'ph-bold ph-check',
    'warning' => 'ph-bold ph-question',
    'cancelled' => 'ph-bold ph-x',
    'expired' => 'ph-bold ph-timer',
    'error' => 'ph-bold ph-exclamation-mark',
    'broken' => 'ph-bold ph-link-break',
    'unknown' => 'ph-bold ph-question',
];
// Where the journey stopped: these steps carry the card's tone and a bold label.
$carbon_pay_current = ['success', 'pending', 'warning', 'cancelled', 'expired', 'error', 'broken'];
$carbon_pay_spinner = '<span class="cp-spin" aria-hidden="true"></span>';
?>
<section class="carbon-pay cp-card cp-status cp-status--<?php echo esc_attr($tone); ?>" aria-labelledby="<?php echo esc_attr($carbon_pay_heading_id); ?>"<?php echo $refresh_url !== '' ? ' data-carbon-refresh="' . esc_url($refresh_url) . '" data-carbon-refresh-seconds="' . (int) $refresh_seconds . '"' : ''; ?>>
    <div class="cp-status__head">
        <span class="cp-status__icon">
            <?php if ($icon === 'spinner') : ?>
                <?php echo $carbon_pay_spinner; // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <?php else : ?>
                <i class="<?php echo esc_attr($icon); ?>" aria-hidden="true"></i>
            <?php endif; ?>
        </span>
        <div class="cp-status__body">
            <h2 class="cp-status__title" id="<?php echo esc_attr($carbon_pay_heading_id); ?>"><?php echo esc_html($title); ?></h2>
            <p class="cp-status__text"><?php echo esc_html($text); ?></p>
        </div>
    </div>

    <ol class="cp-journey" aria-label="مراحل پرداخت">
        <?php foreach ($journey as $carbon_pay_index => $carbon_pay_part) : ?>
            <?php if ($carbon_pay_index % 2 === 1) : ?>
                <li class="cp-journey__line cp-journey__line--<?php echo esc_attr($carbon_pay_part); ?>" aria-hidden="true"></li>
            <?php else : ?>
                <li class="cp-step cp-step--<?php echo esc_attr($carbon_pay_part); ?><?php echo in_array($carbon_pay_part, $carbon_pay_current, true) ? ' is-current' : ''; ?>"<?php echo in_array($carbon_pay_part, $carbon_pay_current, true) ? ' aria-current="step"' : ''; ?>>
                    <span class="cp-step__dot">
                        <?php if ($carbon_pay_part === 'pending') : ?>
                            <?php echo $carbon_pay_spinner; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <?php elseif (isset($carbon_pay_step_icons[$carbon_pay_part])) : ?>
                            <i class="<?php echo esc_attr($carbon_pay_step_icons[$carbon_pay_part]); ?>" aria-hidden="true"></i>
                        <?php endif; ?>
                    </span>
                    <span class="cp-step__label"><?php echo esc_html($carbon_pay_labels[intdiv($carbon_pay_index, 2)]); ?></span>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>

    <?php if ($reference !== '') : ?>
        <div class="cp-ref">
            <span class="cp-ref__label" id="<?php echo esc_attr($carbon_pay_heading_id); ?>-ref">کد پیگیری کربن</span>
            <span class="cp-ref__value">
                <b class="cp-ref__code" dir="ltr" aria-labelledby="<?php echo esc_attr($carbon_pay_heading_id); ?>-ref"><?php echo esc_html($reference); ?></b>
                <button type="button" class="cp-icon-btn cp-icon-btn--ghost" data-copy="<?php echo esc_attr($reference); ?>" aria-label="کپی کد پیگیری"><i class="ph ph-copy" aria-hidden="true"></i></button>
                <span class="cp-visually-hidden" aria-live="polite"></span>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($note !== null) : ?>
        <p class="cp-callout cp-callout--<?php echo esc_attr($note['tone']); ?>">
            <i class="ph-fill <?php echo $note['tone'] === 'warning' ? 'ph-warning' : 'ph-shield-check'; ?>" aria-hidden="true"></i>
            <span><?php echo esc_html($note['text']); ?></span>
        </p>
    <?php endif; ?>

    <div class="cp-status__foot">
        <div class="cp-status__amount">
            <span class="cp-muted"><?php echo esc_html($amount_label); ?></span>
            <span class="cp-status__money">
                <span class="cp-money"><span class="cp-money__num"><?php echo esc_html($amount); ?></span><span class="cp-money__unit"><?php echo esc_html($unit); ?></span></span>
                <?php if ($paid_badge) : ?>
                    <span class="cp-badge cp-badge--success"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>پرداخت شد</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="cp-status__actions">
            <?php if ($refresh_url !== '') : ?>
                <span class="cp-muted">بررسی بعدی تا <b class="cp-num" data-carbon-countdown><?php echo esc_html(Format::persianDigits((string) $refresh_seconds)); ?></b> ثانیه</span>
            <?php endif; ?>
            <?php foreach ($actions as $carbon_pay_action) :
                // The Carbon app opens in a new tab with a trailing «↖»; store actions lead with their icon.
                $carbon_pay_external = $carbon_pay_action['target'] === 'app';
                $carbon_pay_icon = $carbon_pay_action['icon'] !== '' ? '<i class="ph ' . esc_attr($carbon_pay_action['icon']) . '" aria-hidden="true"></i>' : '';
                ?>
                <a class="<?php echo $carbon_pay_action['style'] === 'link' ? 'cp-link-quiet' : 'cp-btn' . ($carbon_pay_action['style'] === 'outline' ? ' cp-btn--outline' : ''); ?>" href="<?php echo esc_url($carbon_pay_action['url']); ?>"<?php echo $carbon_pay_external ? ' target="_blank" rel="noopener"' : ''; ?>>
                    <?php echo $carbon_pay_external ? '' : $carbon_pay_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <?php echo esc_html($carbon_pay_action['label']); ?>
                    <?php echo $carbon_pay_external ? $carbon_pay_icon : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
