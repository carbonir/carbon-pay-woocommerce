# Publishing on WordPress.org

Slug: **`carbon-pay-for-woocommerce`** (derived from the plugin name «Carbon Pay for WooCommerce»; the directory rejects slugs that only contain "woocommerce"). The text domain and the folder inside the release zip already use it.

## Done in the code

- [x] All bundled assets are GPL-compatible: Vazirmatn (SIL OFL 1.1, `assets/fonts/OFL.txt`) and Phosphor Icons (MIT). The commercial Ravi fonts were removed.
- [x] English plugin header and `readme.txt`, with an **External services** section describing what is sent to Carbon, when, and where.
- [x] Text domain matches the slug.
- [x] [Plugin Check](https://wordpress.org/plugins/plugin-check/) passes on the release build with no errors or warnings.
- [x] Release zip excludes tests, dev tooling, docs and vendor (`.gitattributes` export-ignore).

To re-run Plugin Check after changes:

```bash
../carbon-test-shop/shop.sh wp plugin install plugin-check --activate
bin/build-zip.sh && unzip -o dist/carbon-pay-for-woocommerce-*.zip -d /tmp/cpw && \
  podman cp /tmp/cpw/carbon-pay-for-woocommerce carbon-shop-wp:/var/www/html/wp-content/cpw-check
../carbon-test-shop/shop.sh wp plugin check /var/www/html/wp-content/cpw-check
```

## Still to do (people, not code)

1. **Privacy policy.** carbon.ir has terms (`/fa/terms-and-conditions`) but no privacy policy page. Reviewers expect both links in the External services section, because the plugin sends payers' mobile numbers and national codes to Carbon. Publish one and add the link to `readme.txt`.
2. [x] **WordPress.org account.** [`carbonir`](https://profiles.wordpress.org/carbonir/) is in `Contributors:` in `readme.txt`. Submit the plugin and answer the review emails from this account.
3. **Persian interface strings.** Payer- and admin-facing text is written directly in Persian, not wrapped in `__()`. That is allowed, but reviewers may ask for English source strings with a Persian translation. If they do, wrap the strings with the `carbon-pay-for-woocommerce` text domain and move the Persian text into the fa_IR translation.
4. **Persian listing.** After approval, translate the readme on translate.wordpress.org using `docs/readme-fa.txt`, and the plugin header (name, description), so Persian wp-admin shows «افزونه پرداخت کربن برای ووکامرس» again.
5. **Assets for the directory page** are ready in `.wordpress-org/` (banners, icons and `screenshot-1.png`…`screenshot-6.png`, matching `== Screenshots ==` in `readme.txt`). After approval, copy them into the SVN `assets/` folder, not into `trunk/`.
6. **Submit** the zip from `bin/build-zip.sh` at https://wordpress.org/plugins/developers/add/. Review usually takes a few weeks; answer the reviewer's email from the same account.
7. **After approval**, commit to SVN `trunk/`, tag `tags/1.0.0/`, and keep `Stable tag` in `readme.txt` in step with the version.
