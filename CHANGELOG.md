# Changelog

## 1.0.0

First public release. Replaces `gsmpay-woocommerce` 1.1.0.

### Payments
- Pay with Carbon purchasing power (cash, gold and silver, crypto, credit and instalments) through the Carbon CPG API (`/v1/cpg/payments`, `/verify`).
- Classic and block checkout; HPOS compatible. The block checkout place-order button reads «پرداخت با کربن».
- The payment result comes from verify only. A `?status=failed` query never cancels an order the bank has paid.
- Failed payments set the order to `failed` and keep it payable. Result cards cover paid, pending (re-checked automatically), status unknown («بررسی دوباره»), cancelled, expired (20-minute token) and failed.
- Server-to-server callbacks get a JSON 200 with no redirect and no session notices.
- Payer errors (Shahkar mismatch, inquiry outage, minimum amount, name script) are shown next to the field. Merchant-side errors (merchant code, IP whitelist, inactive gateway) are hidden from payers and logged under source `carbon-pay`.
- Payment tokens are reused when the hand-off page reloads, and every token issued for an order is accepted on callback.
- Refunds are not sent to Carbon. WooCommerce can still record a manual refund.

### Admin
- Settings: on/off switch and merchant code. The title and description are fixed Carbon copy, and the plugin always uses `api.carbon.ir` (developers can change it with the `carbon_pay_api_base` filter).
- «بررسی اتصال» checks the merchant code as typed against Carbon and reports whether the code is accepted, the gateway is inactive, the server IP is not whitelisted, or Carbon is unreachable. Nothing is created at Carbon.
- The settings show the return URL to register in the Carbon merchant panel.
- A «پرداخت کربن» box on the order screen shows the payer details, the payment status and the Carbon reference.

### Migration from GSMPay
- Settings and orders move to Carbon Pay automatically, in batches through Action Scheduler. In-flight GSMPay payments still complete. Legacy data is kept for rollback. See [docs/migration.md](docs/migration.md).

### Design
- One scoped stylesheet (`.carbon-pay`, no `!important`) that works on light and dark storefronts and right-to-left themes.
- Bundled, GPL-compatible assets: Vazirmatn font (SIL OFL 1.1) and a Phosphor Icons subset (MIT). No CDN.

### Quality
- PHPUnit on PHP 7.4, 8.3 and 8.5, node:test for the scripts, and a WordPress + WooCommerce integration smoke test (`tests/integration/run.sh`).
