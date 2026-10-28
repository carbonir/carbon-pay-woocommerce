#!/usr/bin/env sh
# Builds dist/carbon-pay-for-woocommerce-<version>.zip from the committed tree (see .gitattributes export-ignore).
# The folder inside the zip is the WordPress.org slug, carbon-pay-for-woocommerce.
set -eu
cd "$(dirname "$0")/.."
version=$(sed -n 's/^ \* Version: *//p' carbon-pay-woocommerce.php)
mkdir -p dist
git archive --format=zip --prefix=carbon-pay-for-woocommerce/ -o "dist/carbon-pay-for-woocommerce-$version.zip" HEAD
echo "dist/carbon-pay-for-woocommerce-$version.zip"
