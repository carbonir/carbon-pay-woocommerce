<?php
/**
 * Payment option label: mark · «کربن» | «پرداخت با قدرت خرید» (design 2a).
 * Classic checkout prints it from Gateway::get_icon() next to the plain title, which carbon-pay.css
 * hides; aria-hidden so screen readers read the title once. Mirrored in assets/js/checkout-blocks.js.
 */
defined('ABSPATH') || exit;
?>
<span class="carbon-pay cp-option-label" aria-hidden="true"><span class="cp-mark"></span><b class="cp-option-label__brand">کربن</b><span class="cp-option-label__sep"></span><span class="cp-option-label__text">پرداخت با قدرت خرید</span></span>
