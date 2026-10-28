# Architecture

## Payment flow

```
checkout ──process_payment()──► POST /v1/cpg/payments ──► order-pay handoff ──► hosted Carbon page
   ▲  validation / Carbon errors shown inline                                         │
   │                                                                                   ▼
   └──── pay again ◄── status block ◄── verify ◄── GET /wc-api/wc_carbon_pay/?wc_order&key&status&token
                                         ▲
                     carbon-service orders:webhook (same URL, server to server → JSON 200)
```

- **Create** (`Gateway::process_payment` → `PaymentStarter`): validates mobile, national code and Persian billing names the same way carbon-service does, then creates the Carbon payment. Errors come back to the checkout. A fresh token is reused when the handoff page is reloaded.
- **Return** (`ReturnHandler` + `ReturnResolver`): the outcome comes **from verify only**. `?status=` is used to tell "still verifying" from "cancelled", never to decide paid vs. not paid (backend issue #8 sends `failed` for bank-paid orders).
- **Failures** set the order to **failed**, never cancelled, so a late webhook or "pay again" can still complete it (backend issue #9).
- **Webhook**: requests without `Accept: text/html` get a JSON response instead of a redirect, and no session notices.
- **Screens** (design «Carbon WooCommerce Gateway»): checkout option 2a (label `checkout/label.php`, chips `partials/power-sources.php`, mirrored in `checkout-blocks.js`), hand-off 1d (`checkout/handoff.php`, 3-second countdown), result cards 1f–1l (`order/status.php`, copy in `StatusView::variant()`). Browser returns add no WooCommerce notice, because the card already says it; only a callback for an unknown order adds one (on the checkout page).
- **Pay again** goes to the order-pay receipt step, which starts a fresh Carbon payment (`Handoff::prepare`); «روش پرداخت دیگر» goes to WooCommerce's pay-for-order form.
- **No refunds**: the gateway supports `products` only, so WooCommerce offers just «بازپرداخت دستی» and never calls Carbon. The plugin does not use `POST /v1/cpg/payments/reverse`. Orders refunded through Carbon by pre-1.0 builds keep `carbon_status = refund` and show as refunded in the metabox.
- **Amounts** are sent to Carbon in Rial; `Support\Money` converts from the store currency and rounds: IRR ×1, IRT ×10, IRHR ×100, IRHT ×1 000. The IRHR/IRHT factors are carried over from GSMPay 1.x and do not match their labels («هزار ریال» = 1 000 Rial, «هزار تومان» = 10 000 Rial); see Known limitations.

### API error kinds

`Api\ApiException` maps every Carbon response to a kind, and `Payment\ErrorPresenter` decides who sees it:

On payment creation, only `validation` errors reach the payer, as specific messages: Shahkar mismatch and inquiry outage (on `national_code`), minimum amount (`invoice_amount`), non-Persian names (`payer_first_name` / `payer_last_name`). Every other kind (`network`, `merchant`, `ip`, `gateway_inactive`, `not_allowed`, `rate_limited`, `server`, `malformed`, …) shows the generic «ارتباط با کربن برقرار نشد.». Every create-payment failure, validation included, is logged with source `carbon-pay`.

## Code layout

```
carbon-pay-woocommerce.php   bootstrap + autoloader
src/
  Plugin.php    hook wiring, callback URL, server IP, logging
  Settings.php  GatewayId.php  Assets.php
  Api/          CarbonApi (create / verify), WpHttpClient, Response, ApiException (error kinds)
  Payment/      PaymentRequestBuilder, PaymentStarter, ReturnResolver/Handler/Outcome/Response,
                ErrorPresenter, PayerError
  Checkout/     PayerDetails (normalise + validate payer identity)
  Gateway/      Gateway (WC_Payment_Gateway)
  Blocks/       BlocksSupport, CheckoutFields (block checkout)
  Frontend/     Handoff (order-pay), StatusView (thank-you / view-order)
  Admin/        SettingsPage, OrderScreen (payer details, «پرداخت کربن» metabox)
  Currency/     Currencies (IRHR / IRHT registration)
  Order/        OrderMeta (meta keys)
  Migration/    LegacyMigration (GSMPay → Carbon)
  Support/      PersianText, Validation, Money, Format
  View/         Template (theme-overridable renderer)
templates/      markup from the «Carbon WooCommerce Gateway» design (override under {theme}/carbon-pay/)
assets/         carbon-pay.css (only shipped stylesheet), fonts, logos, Phosphor subset, js/
tests/          Unit/ (PHPUnit), js/ (node:test), Stubs/ (WooCommerce stand-ins), integration/ (Podman smoke test)
bin/            build-zip.sh, build-icons.mjs, preview.php; not shipped
```

## Hooks

| Filter | Default | Use |
|---|---|---|
| `carbon_pay_api_base` | `https://api.carbon.ir` | Point at `stage-api.carbon.ir`, `dev-api.carbon.ir` or a local carbon-service. There is no environment setting. |
| `carbon_pay_token_ttl` | `1200` | Must match `CARBON_GATEWAY_ONLINE_TIMEOUT` (seconds) |
| `carbon_pay_http_client` | `WpHttpClient` | Inject an `Api\HttpClient` |
| `carbon_pay_settings_links` | carbon.ir | Merchant-panel and docs URLs in the settings header |
| `carbon_pay_form_fields` | enabled, merchant code | Gateway settings fields. The title is fixed (`Settings::TITLE`) even if a field is added. |
| `carbon_pay_icon` | `assets/logos/carbon-pay-icon.svg` | Gateway icon in wp-admin. The storefront shows the designed label (`templates/checkout/label.php`) instead. |

## Known limitations

- **Open question:** IRHR and IRHT convert with ×100 and ×1 000 (inherited from GSMPay), which is 10× less than «هزار ریال» / «هزار تومان» imply. A store priced in those currencies would send a tenth of the price to Carbon. Confirm the intended factors before testing those currencies.
- Block checkout: mobile and national code are registered as optional so they never block other methods; they are enforced in `process_payment()` when Carbon is chosen. WooCommerce still labels them «(اختیاری)» while Carbon is selected.
- `woocommerce_receipt_*` runs after headers are sent, so the handoff uses JS + `<noscript>` meta refresh instead of a server redirect.
- Fonts (Vazirmatn variable, Phosphor woff2) are shipped whole and load only on checkout, order and settings pages. Vazirmatn has no Persian-digit variant, so amounts are converted to Persian digits in PHP (`Support\Format`).
