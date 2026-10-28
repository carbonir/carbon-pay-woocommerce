=== Carbon Pay for WooCommerce ===
Contributors: carbonir
Tags: woocommerce, payment gateway, iran, bnpl, carbon
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments with Carbon purchasing power: cash, gold and silver holdings, and Carbon credit and instalments.

== Description ==

Carbon Pay connects your WooCommerce store to the Carbon payment gateway (CPG). Your customers pay with their Carbon purchasing power, which combines cash, gold and silver holdings, and credit or instalments, and you are settled immediately.

* Classic and block checkout
* HPOS (High-Performance Order Storage) compatible
* Automatic migration of settings and orders from the GSMPay plugin
* Persian (fa_IR) interface, designed for right-to-left stores

= Requirements =

* WooCommerce 8.9 or later (tested up to 11.2)
* Store currency Iranian Rial (IRR), Toman (IRT), thousand Rial (IRHR) or thousand Toman (IRHT). The gateway hides itself for other currencies.
* An active gateway in the Carbon merchant panel, with your server's IP address whitelisted

== External services ==

This plugin connects to the Carbon payment gateway API, operated by Carbon (https://carbon.ir), to create and verify payments. Without it the plugin cannot take payments.

* **When:** when a customer places an order with Carbon Pay, when the customer returns from the Carbon payment page (or Carbon notifies your store) and when the store re-checks a payment.
* **Where:** `https://api.carbon.ir`. The customer is then redirected to Carbon's hosted payment page.
* **What is sent:** your merchant code; the order number, date and total; the order's line items, fees and shipping (name, quantity, price, discount, tax); your store's return URL; and the customer's first name, last name, mobile number and national ID code, which Carbon requires to identify the payer. Verification requests send the payment token, the order number and the amount.

* **Terms of use:** https://carbon.ir/fa/terms-and-conditions
* **Privacy policy:** https://carbon.ir/fa/terms-and-conditions#art8 (article 8, privacy and data security)

== Installation ==

1. Install and activate the plugin. WooCommerce must be active.
2. Go to WooCommerce › Settings › Payments and open "Carbon Pay".
3. Enter your merchant code.
4. Register the return URL and the server IP shown below the settings in the Carbon merchant panel.

== Frequently Asked Questions ==

= Why doesn't Carbon Pay appear at checkout? =

The gateway is only shown when it is enabled, a merchant code is set, and the store currency is IRR, IRT, IRHR or IRHT.

= Customers see "Could not connect to Carbon". =

Usually the merchant code is wrong or your server's IP is not whitelisted in the Carbon merchant panel. The details are logged under WooCommerce › Status › Logs, source `carbon-pay`.

= How do I test before going live? =

Place a small order with Carbon Pay. Developers can point the plugin at another Carbon API with the `carbon_pay_api_base` filter.

= Can I change the payment method's title or description? =

No. Customers see the same Carbon name and copy in every store, so the title and description are fixed.

= Can I refund a Carbon order from WooCommerce? =

No. Refunds are handled with Carbon directly. WooCommerce can still record a manual refund on the order.

= I used the GSMPay plugin. =

Activate Carbon Pay, then deactivate GSMPay. Settings and orders are migrated automatically, and payments that were in progress still complete.

== Credits ==

* Vazirmatn font by Saber Rastikerdar and contributors, SIL Open Font License 1.1.
* Phosphor Icons, MIT License.

== Screenshots ==

1. Carbon Pay at checkout: the customer's Carbon purchasing power (cash, gold and silver, crypto, credit and instalments).
2. Hand-off to the Carbon payment page, with a short countdown and a button to continue.
3. Payment result: paid, with the Carbon reference.
4. Payment result: status unknown, with a safe "check again" action that never invites a double charge.
5. Gateway settings with the return URL and the connection check.
6. The Carbon Pay box on the order screen.

== Changelog ==

= 1.0.0 =
* First public release.
* Pay with Carbon purchasing power: cash, gold and silver holdings, credit and instalments.
* Classic and block checkout, HPOS compatible.
* Clear result pages for every payment outcome: paid, pending, status unknown, cancelled, expired and failed.
* Connection check in the settings for the merchant code and the server IP whitelist.
* Automatic migration of settings and orders from the GSMPay plugin.

== Upgrade Notice ==

= 1.0.0 =
First public release. If you use the GSMPay plugin, activate Carbon Pay and then deactivate GSMPay.
