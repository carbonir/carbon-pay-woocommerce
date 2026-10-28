#!/usr/bin/env sh
# Integration smoke test: WordPress + WooCommerce + this plugin in Podman (or Docker with ENGINE=docker).
# The Carbon API is replaced by tests/integration/mu-plugins/carbon-pay-fake-api.php.
#
#   tests/integration/run.sh            # runs and tears down
#   KEEP=1 tests/integration/run.sh     # leaves the stack up on http://localhost:8099 (admin / admin)
#   WC_ZIP=/path/woocommerce.zip …       # install WooCommerce from a local zip (no wordpress.org access)
set -eu

ENGINE=${ENGINE:-podman}
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
POD=cpw
PORT=${PORT:-8099}
URL="http://localhost:$PORT"
DB_IMAGE=${DB_IMAGE:-docker.io/library/mysql:8.4.13}
WP_IMAGE=${WP_IMAGE:-docker.io/library/wordpress:php8.3-apache}
CLI_IMAGE=${CLI_IMAGE:-docker.io/library/wordpress:cli-php8.3}

cleanup() {
    if [ "${KEEP:-0}" != "1" ]; then
        $ENGINE pod rm -f $POD >/dev/null 2>&1 || true
        $ENGINE volume rm -f cpw-html >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT
cleanup

DB_ENV="-e WORDPRESS_DB_HOST=127.0.0.1 -e WORDPRESS_DB_USER=root -e WORDPRESS_DB_PASSWORD=root -e WORDPRESS_DB_NAME=wp"
MOUNTS="-v cpw-html:/var/www/html -v $ROOT:/var/www/html/wp-content/plugins/carbon-pay-for-woocommerce:ro,z -v $ROOT/tests/integration/mu-plugins:/var/www/html/wp-content/mu-plugins:ro,z"

$ENGINE pod create --name $POD -p "$PORT:80" >/dev/null
$ENGINE run -d --pod $POD --name cpw-db -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=wp "$DB_IMAGE" >/dev/null
$ENGINE run -d --pod $POD --name cpw-wp $DB_ENV $MOUNTS "$WP_IMAGE" >/dev/null

wp() {
    $ENGINE run --rm --pod $POD --user 33:33 $DB_ENV $MOUNTS "$CLI_IMAGE" wp "$@"
}

echo "waiting for WordPress files and database…"
i=0
# `wp core is-installed` exits 1 on an empty database (possibly with PHP warnings), and prints
# an "Error:" line while wp-config.php is missing or MySQL is still starting.
db_ready() {
    out=$(wp core is-installed 2>&1) && return 0
    ! echo "$out" | grep -q 'Error:'
}
until db_ready; do
    i=$((i + 1)); [ $i -gt 60 ] && { echo "database did not come up"; exit 1; }
    sleep 3
done

wp core install --url="$URL" --title="فروشگاه موبایل" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email >/dev/null
wp rewrite structure '/%postname%/' >/dev/null
if [ -n "${WC_ZIP:-}" ]; then
    $ENGINE run --rm --pod $POD --user 33:33 $DB_ENV $MOUNTS -v "$WC_ZIP:/tmp/woocommerce.zip:ro,z" "$CLI_IMAGE" \
        wp plugin install /tmp/woocommerce.zip --activate >/dev/null
else
    wp plugin install woocommerce --activate >/dev/null
fi
echo "$(wp plugin get woocommerce --field=version) WooCommerce installed"

# A GSMPay 1.x installation to migrate from.
wp option update woocommerce_WC_GSMPay_settings '{"enabled":"yes","title":"جی‌اس‌ام پی","description":"x","merchant_code":"MRC-LEGACY"}' --format=json >/dev/null
wp plugin activate carbon-pay-for-woocommerce >/dev/null

wp eval-file wp-content/plugins/carbon-pay-for-woocommerce/tests/integration/smoke.php setup > /tmp/cpw-setup.log 2>&1 || { grep -v HTTP_HOST /tmp/cpw-setup.log; exit 1; }
grep -v HTTP_HOST /tmp/cpw-setup.log
CALLBACK=$(sed -n 's/^callback //p' /tmp/cpw-setup.log)

# Carbon's orders:webhook: plain GET, no Accept: text/html, status=failed (backend issue #8).
BODY=$(curl -sS -H 'Accept: */*' "$(echo "$CALLBACK" | sed "s#^http://localhost:$PORT#$URL#")")
echo "webhook response: $BODY"
case "$BODY" in
    *'"outcome":"paid"'*) echo "PASS webhook answered JSON outcome=paid" ;;
    *) echo "FAIL webhook response"; exit 1 ;;
esac

wp eval-file wp-content/plugins/carbon-pay-for-woocommerce/tests/integration/smoke.php after-callback

# Front-end smoke: the plugin must not break the storefront or admin.
code=$(curl -sS -o /dev/null -w '%{http_code}' "$URL/")
[ "$code" = "200" ] && echo "PASS storefront 200" || { echo "FAIL storefront $code"; exit 1; }

echo "integration smoke test passed"
