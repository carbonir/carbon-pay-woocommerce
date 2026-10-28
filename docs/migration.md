# Migration from GSMPay

Carbon Pay 1.0 replaces `gsmpay-woocommerce` 1.x. Activate Carbon Pay, then deactivate GSMPay. The migration (`LegacyMigration`) runs automatically on the first request after activation:

| GSMPay 1.x | Carbon Pay 1.0 |
|---|---|
| option `woocommerce_WC_GSMPay_settings` | `woocommerce_WC_Carbon_Pay_settings`: enabled + merchant code copied |
| order payment method `WC_GSMPay` | `WC_Carbon_Pay`, in batches of 50 via Action Scheduler |
| order meta `gsmpay_token` | `carbon_token` (+ `carbon_tokens`) |
| callback `?wc-api=WC_GSMPay` | still answered (while the old plugin is inactive), so in-flight payments complete |
| block fields `gsmpay/mobile`, `gsmpay/national_code` | `carbon/mobile`, `carbon/national_code` |

Upgrading from a pre-1.0 Carbon Pay build drops the `title`, `description` and `environment` keys from `woocommerce_WC_Carbon_Pay_settings`: the title is fixed and the plugin always uses `api.carbon.ir`.

Legacy options and meta are not deleted, so you can roll back. While both plugins are active, an admin notice asks you to deactivate the old one.

## Checking a migration

- **ووکامرس › وضعیت › کارهای زمان‌بندی‌شده**: the `carbon_pay_migrate_orders` actions should all be complete.
- Old orders show «کربن» as the payment method and keep their token in the «پرداخت کربن» metabox.
- The integration smoke test (`tests/integration/run.sh`) starts from a GSMPay installation and checks all of the above.
