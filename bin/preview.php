<?php
/**
 * Dev only: renders every tier-A template with sample data into one static HTML page, so the
 * output can be compared with carbon-pay-ui without a WordPress install.
 *
 *   php bin/preview.php > .preview/index.html
 *   php bin/preview.php 2 3 > .preview/part.html   (only sections 2..3, zero-based)
 */

use Carbon\Pay\Frontend\StatusView;
use Carbon\Pay\Payment\ErrorPresenter;
use Carbon\Pay\Payment\PayerError;
use Carbon\Pay\Payment\ReturnOutcome;
use Carbon\Pay\View\Template;

define('ABSPATH', __DIR__);
define('CARBON_PAY_URL', '../');

$root = dirname(__DIR__);
spl_autoload_register(static function ($class) use ($root) {
    if (strpos($class, 'Carbon\\Pay\\') === 0) {
        require $root . '/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
    }
});

function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function wp_kses_post($s) { return (string) $s; }
function apply_filters($hook, $value) { return $value; }
function wp_unique_id($prefix = '') { static $i = 0; return $prefix . (++$i); }

$sections = [];

$sections['Checkout label'] = Template::render('checkout/label');
$sections['Classic checkout — empty'] = Template::render('checkout/fieldset', [
    'gateway_id' => 'WC_Carbon_Pay', 'mobile' => '', 'national_code' => '', 'errors' => [],
]);
$sections['Classic checkout — shahkar + inquiry'] = Template::render('checkout/fieldset', [
    'gateway_id' => 'WC_Carbon_Pay', 'mobile' => '09121234567', 'national_code' => '0012345679',
    'errors' => [
        new PayerError(PayerError::CODE_INQUIRY, null, 'warning', 'سرویس استعلام در حال حاضر در دسترس نیست.', 'چند دقیقه دیگر دوباره «پرداخت با کربن» را بزنید.', ''),
        new PayerError(PayerError::CODE_SHAHKAR, PayerError::FIELD_NATIONAL_CODE, 'error', 'متاسفانه کد ملی وارد شده با شماره موبایل مطابقت ندارد.', '', ''),
    ],
]);
$sections['Classic checkout — required'] = Template::render('checkout/fieldset', [
    'gateway_id' => 'WC_Carbon_Pay', 'mobile' => '', 'national_code' => '',
    'errors' => [ErrorPresenter::required(PayerError::FIELD_MOBILE), ErrorPresenter::required(PayerError::FIELD_NATIONAL_CODE)],
]);
$sections['Order-pay handoff'] = Template::render('checkout/handoff', [
    'redirect_url' => 'https://cpg.carbon.ir/payments/9f3c1a7e', 'host' => 'cpg.carbon.ir', 'order_number' => '۱۲۸۷', 'delay' => 3,
]);
$outcomes = [ReturnOutcome::PAID, ReturnOutcome::PENDING, ReturnOutcome::API_ERROR, ReturnOutcome::CANCELLED, ReturnOutcome::EXPIRED, ReturnOutcome::UNVERIFIED, ReturnOutcome::INVALID_TOKEN];
foreach ($outcomes as $outcome) {
    $v = StatusView::variant($outcome, 20);
    $sections['Status — ' . $outcome] = Template::render('order/status', [
        'tone' => $v['tone'], 'icon' => $v['icon'], 'title' => $v['title'], 'text' => $v['text'],
        'journey' => $v['journey'], 'note' => $v['note'],
        'reference' => $outcome === ReturnOutcome::PAID ? '9f3c1a7e-52b0-4d1e-8a77-1c02be6f4a19' : '',
        'amount_label' => $outcome === ReturnOutcome::PAID ? 'مبلغ پرداخت‌شده' : 'مبلغ سفارش',
        'amount' => '۲۱٬۳۹۶٬۰۰۰', 'unit' => 'تومان', 'paid_badge' => $outcome === ReturnOutcome::PAID,
        'actions' => array_map(static function ($a) { return $a + ['url' => '#']; }, $v['actions']),
        'refresh_url' => $outcome === ReturnOutcome::PENDING ? '#' : '', 'refresh_seconds' => StatusView::REFRESH_SECONDS,
    ]);
}
$sections['Admin — settings header'] = Template::render('admin/settings-header');
$sections['Admin — connection info'] = Template::render('admin/connection-info', [
    'callback_url' => 'https://shop.example.com/wc-api/wc_carbon_pay/',
]);
$sections['Admin — metabox (paid)'] = Template::render('admin/order-metabox', [
    'status' => 'paid', 'amount_rial' => '۲۱۳٬۹۶۰٬۰۰۰',
    'verified_at' => '۳۰ مهر ۱۴۰۵، ۱۴:۲۲', 'token' => '9f3c1a7e-52b0-4d1e-8a77-1c02be6f4a19', 'token_short' => '9f3c1a7e-…4a19',
    'missing_merchant' => false, 'settings_url' => '#',
]);

if (isset($argv[1])) {
    $from = (int) $argv[1];
    $to = isset($argv[2]) ? (int) $argv[2] : $from;
    $sections = array_slice($sections, $from, $to - $from + 1, true);
}

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Carbon Pay preview</title>
<link rel="stylesheet" href="../assets/vendor/phosphor/phosphor.css">
<link rel="stylesheet" href="../assets/carbon-pay.css">
<style>body{margin:0;padding:24px;background:#f6f7f7;font-family:Tahoma,sans-serif}section.preview{max-width:640px;margin:0 auto 32px}h1.preview{font:600 13px monospace;color:#888;direction:ltr;text-align:left;margin:0 0 8px}</style>
</head>
<body>
<?php foreach ($sections as $title => $html) : ?>
<section class="preview"><h1 class="preview"><?php echo esc_html($title); ?></h1><?php echo $html; ?></section>
<?php endforeach; ?>
</body>
</html>
