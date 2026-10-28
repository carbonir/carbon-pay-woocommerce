# Development

## Test shop

The manual test shop is a separate project next to this repo: [`../carbon-test-shop`](../../carbon-test-shop/README.md). It runs a Persian, RTL WordPress + WooCommerce + Storefront store in Podman with products, shipping, other payment methods, customers and past orders, mounts this plugin live and read-only, and fakes the Carbon CPG so every return state can be reached from the browser:

```bash
../carbon-test-shop/shop.sh up                  # http://localhost:8088, admin / admin, customer / customer
../carbon-test-shop/shop.sh checkout classic    # or blocks (default)
CARBON_MERCHANT_CODE=MRC-xxxx CARBON_PAY_API_BASE=https://stage-api.carbon.ir ../carbon-test-shop/shop.sh up   # a real Carbon environment
```

## Pointing the plugin at another Carbon API

The plugin has no environment setting: it always calls `https://api.carbon.ir`. For stage, dev or a local carbon-service, use the filter (the test shop sets it from `CARBON_PAY_API_BASE`):

```php
add_filter('carbon_pay_api_base', fn () => 'https://stage-api.carbon.ir');
```

### Manual test checklist

Run each on both checkouts (`shop.sh checkout classic` / `blocks`), logged in as `customer` or as a guest. With the fake CPG (`shop.sh carbon fake`), the hosted page has one button per outcome; see the shop README for the error-triggering checkout data.

| Scenario | Expected |
|---|---|
| Pay a normal order | Hand-off card with a 3-second countdown → Carbon page → thank-you page with the paid card (Carbon reference + copy); order **processing**, transaction id = Carbon token, «پرداخت کربن» metabox shows paid amount |
| Cancel on the Carbon page | Neutral «پرداخت را در کربن لغو کردید» card with «پرداخت دوباره با کربن» and «روش پرداخت دیگر», no WooCommerce notice; order **failed** (not cancelled) |
| Verify pending (fake: «تایید با تاخیر») | Spinner card, «بررسی بعدی تا N ثانیه» counts down, the page re-checks every 10 s and turns paid |
| Verify fails (fake: «قطعی ارتباط») | Amber «وضعیت پرداخت هنوز مشخص نیست» card; «بررسی دوباره» is the primary action, «پرداخت دوباره» is demoted |
| Return with a foreign token (fake: «توکن نامعتبر») | Red «این پرداخت قابل شناسایی نبود» card, order untouched |
| Pay again after a failure | New token, order completes |
| Reload the handoff page | Same token reused, no second payment created |
| Mobile and national code that don't match (Shahkar) | Inline error on the national code field, no redirect |
| English billing name | «نام و نام خانوادگی فقط باید شامل حروف فارسی باشد.» |
| کالای ارزان (below minimum) | «مبلغ سفارش کمتر از حداقل مجاز کربن است.» |
| Wrong merchant code / IP not whitelisted | Payer sees the generic error; details in **ووکامرس › وضعیت › گزارش‌ها** (`carbon-pay`) |
| Leave the Carbon page open > 20 min (fake: «منقضی شدن لینک») | Neutral «مهلت پرداخت تمام شد» card mentioning 20 minutes |
| Refund an order (order edit → «بازپرداخت») | Only «بازپرداخت دستی» is offered; nothing is sent to Carbon |
| Webhook (tunnel only) | Close the tab after paying at the bank; order still becomes processing |
| Store currency IRR / IRHT (wp-admin › تنظیمات) | Amount sent to Carbon in Rial; check the open question in [architecture.md](architecture.md#known-limitations) first |

### Migration from GSMPay

To test the upgrade path, install the old plugin into the test shop before activating Carbon Pay:

```bash
SHOP=../carbon-test-shop/shop.sh
$SHOP reset && $SHOP up
$SHOP wp plugin deactivate carbon-pay-for-woocommerce
podman cp ../gsmpay-woocommerce carbon-shop-wp:/var/www/html/wp-content/plugins/   # docker cp with ENGINE=docker
$SHOP wp plugin activate gsmpay-woocommerce
# configure GSMPay in wp-admin and place an order with it, then:
$SHOP wp plugin activate carbon-pay-for-woocommerce
$SHOP wp plugin deactivate gsmpay-woocommerce
```

See [migration.md](migration.md) for what to check.

## Automated tests

```bash
# PHP unit tests (PHPUnit 9.6 + Brain Monkey). No local PHP extensions needed:
podman run --rm -v "$PWD":/app:Z -w /app docker.io/library/composer:2 sh -c 'composer install && vendor/bin/phpunit'

# JS unit tests (node:test, Node 22+)
npm test

# Integration smoke test: throwaway WordPress + WooCommerce in Podman, Carbon API faked by a mu-plugin
# (migration, gateway + block registration, rendering, payment, webhook with status=failed, no Carbon refunds)
tests/integration/run.sh            # WC_ZIP=/path/woocommerce.zip if wordpress.org is unreachable
KEEP=1 tests/integration/run.sh     # leave it running on http://localhost:8099 (admin / admin)
```

CI (`.gitlab-ci.yml`) runs PHP lint + PHPUnit on PHP 7.4, 8.3 and 8.5, and the JS tests on Node 24.

## Templates and assets

```bash
# Preview every tier-A template without WordPress (serve over HTTP: CSS masks are blocked on file://)
php bin/preview.php > .preview/index.html && python3 -m http.server -d . 8765   # → /.preview/

# Rebuild the Phosphor icon subset after using a new icon
npm pack @phosphor-icons/web@2.1.2 && tar xzf phosphor-icons-web-2.1.2.tgz
node bin/build-icons.mjs package/src
```

Templates can be overridden by themes under `{theme}/carbon-pay/`.

## Release

1. Bump the version in `carbon-pay-woocommerce.php` (header and `CARBON_PAY_VERSION`) and `readme.txt` (`Stable tag`). Move the `Unreleased` notes in `CHANGELOG.md` under the new version and add a changelog entry to `readme.txt` (English) and `docs/readme-fa.txt` (Persian).
2. Update `WC tested up to` / `Tested up to` after running `tests/integration/run.sh` against the latest WordPress and WooCommerce.
3. Commit, then `bin/build-zip.sh`. It archives `HEAD`, so uncommitted changes are not included; `tests/`, `bin/`, `docs/` and dev files are excluded by `.gitattributes`.
