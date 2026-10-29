#!/usr/bin/env bash
#
# Full functional suite for WC Moneris Payment Gateway (free). Run before every release:
#
#   bash tests/functional/run.sh
#
# Runs on the plugin's own wp-env test store (tests/env, http://localhost:8894), never on
# the shared MAMP site: Moneris Pro is active there and the free plugin will not load
# beside it. Start the store once with:
#   cd tests/env && npx -y @wordpress/env@11 start
#
# Covers: the WPHEKA > Moneris page and its save through the gateway (every setting, each
# switch both ways, cut-short posts, fields added by other code, nonce and roles, the old
# WooCommerce screen and its redirect), stats and Transactions on HPOS and on posts
# storage, a rollback to the released 3.8.1, the page in a real browser at four widths,
# real Moneris sandbox payments on the classic and block checkout with a previous-day
# refund, the gateway log's redaction, the files loaded before the PHP 7.4 check on PHP
# 5.6 and 7.0, and new PHP errors from this plugin in debug.log.
# Test data uses the "ZZ MON" prefix and is deleted at the end, even after a failure.
#
# Needs: Docker, the wp-env store above, Node with Playwright (this plugin's node_modules,
# or PW_NODE_PATH). Optional: MONERIS_CLI=<cli container name>, PW_CHANNEL=chrome.
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN="$(cd "$HERE/../.." && pwd)"
IN="/var/www/html/wp-content/plugins/wc-moneris-payment-gateway/tests/functional"
STATE="$HERE/.tmp/state"

CLI="${MONERIS_CLI:-}"
if [ -z "$CLI" ]; then
  WEB="$(docker ps --filter publish=8894 --format '{{.Names}}' | head -1)"
  [ -n "$WEB" ] && CLI="${WEB%-wordpress-1}-cli-1"
fi
docker exec "$CLI" true 2>/dev/null || { echo "The test store is not running. Start it: cd tests/env && npx -y @wordpress/env@11 start"; exit 2; }

for candidate in "${PW_NODE_PATH:-}" "$PLUGIN/node_modules"; do
  if [ -n "$candidate" ] && [ -d "$candidate/playwright" ]; then export NODE_PATH="$candidate"; break; fi
done
[ -n "${NODE_PATH:-}" ] || { echo "Playwright not found; run npm install or set PW_NODE_PATH"; exit 2; }

rm -rf "$STATE"; mkdir -p "$STATE"; : > "$STATE/results"
export MONERIS_STATE="$STATE" MONERIS_CLI="$CLI"

wp() { docker exec -e "MONERIS_RESULTS=$IN/.tmp/state/results" "$CLI" wp "$@"; }
# PHP checks record their own cases; show them as they come.
php_check() { wp eval-file "$@" 2>&1 | grep -E '^(PASS|FAIL)\|' | sed 's/^/  /'; }
record() { echo "$1|$2" >> "$STATE/results"; echo "  $1|$2"; }
# A script must finish: a missing "ran to the end" case is a FAIL.
ran() { grep -qxF "PASS|$1: ran to the end" "$STATE/results" || record FAIL "$1: ran to the end -- stopped before its last case"; }
run_node() { local script="$1" tag="$2"; shift 2; node "$HERE/$script" "$@" || record FAIL "$tag exited with an error"; ran "$tag"; }
storage() {
  wp wc hpos sync >/dev/null 2>&1
  wp wc hpos "$1" >/dev/null 2>&1
  local now; now="$(wp wc hpos status 2>/dev/null | grep -i 'HPOS enabled' | grep -c yes)"
  if { [ "$1" = enable ] && [ "$now" = 1 ]; } || { [ "$1" = disable ] && [ "$now" = 0 ]; }; then record PASS "order storage switched ($1 HPOS)"; else record FAIL "order storage switched ($1 HPOS)"; fi
}

LOG=/var/www/html/wp-content/debug.log
LOG_START="$(docker exec "$CLI" sh -c "[ -f $LOG ] && wc -l < $LOG || echo 0" | tr -d ' ')"
HPOS_WAS="$(wp wc hpos status 2>/dev/null | grep -i 'HPOS enabled' | grep -c yes)"

cleanup() {
  echo "== Cleanup"
  php_check "$IN/setup.php" cleanup "$IN/.tmp/state"
  if [ "$HPOS_WAS" = 1 ]; then storage enable; else storage disable; fi
}
trap cleanup EXIT

echo "== Files loaded before the PHP 7.4 check parse on old PHP"
for v in 5.6 7.0; do
  out="$(docker run --rm -v "$PLUGIN:/p:ro" "php:$v-cli" sh -c 'for f in /p/wc-moneris-payment-gateway.php /p/includes/class-wpheka-gateway-moneris.php /p/includes/class-wpheka-moneris-logger.php /p/includes/blocks/class-wpheka-gateway-moneris-blocks-support.php /p/includes/admin/class-wpheka-gateway-moneris-deactivation.php /p/includes/admin/class-wpheka-gateway-moneris-donation.php /p/includes/wpheka-moneris-api/src/Gateway.php; do php -l "$f" >/dev/null 2>&1 || echo "$f"; done' 2>&1 | grep '^/p/')"
  if [ -z "$out" ]; then record PASS "PHP $v parses every file loaded before the version check"; else record FAIL "PHP $v parses every file loaded before the version check -- $out"; fi
done
if grep -q "version_compare(PHP_VERSION, '7.4', '>=')" "$PLUGIN/wc-moneris-payment-gateway.php" && [ "$(grep -c "wpheka-ui/load.php\|class-wpheka-gateway-moneris-admin-page.php" "$PLUGIN/wc-moneris-payment-gateway.php")" = 2 ]; then
  record PASS "the admin page and kit load only behind the PHP 7.4 check"
else
  record FAIL "the admin page and kit load only behind the PHP 7.4 check"
fi
grep -q "define('WPHEKA_MONERIS_MIN_PHP_VER', '5.6.0')" "$PLUGIN/wc-moneris-payment-gateway.php" && record PASS "runtime PHP minimum stays 5.6, so the gateway always registers" || record FAIL "runtime PHP minimum stays 5.6, so the gateway always registers"

echo "== Block checkout card formatting"
node "$HERE/card-format.test.mjs" "$STATE/results" | grep -v '^  PASS' ; ran "card-format.test.mjs"
grep -q 'cleave' "$PLUGIN/assets/js/block/frontend/payment-block.js" && record FAIL "built block script no longer contains Cleave.js" || record PASS "built block script no longer contains Cleave.js"

echo "== Store set-up (HPOS on)"
wp wc hpos compatibility-mode enable >/dev/null 2>&1
storage enable
php_check "$IN/setup.php" setup "$IN/.tmp/state"

echo "== Settings page and save"
php_check "$IN/settings.php"; ran "settings.php"
echo "== Existing checks"
for f in supports.php refund-message.php; do
  out="$(wp eval-file "$IN/$f" 2>&1)"
  echo "$out" | grep -E '^(PASS|FAIL)  ' | sed -E 's/^(PASS|FAIL)  /\1|'"$f"': /' | tee -a "$STATE/results" | sed 's/^/  /'
  echo "$out" | grep -qE '^(PASS|FAIL)  ' || record FAIL "$f printed no cases"
done
echo "== Stats and Transactions (HPOS)"
php_check "$IN/stats.php" hpos; ran "stats.php (hpos)"
echo "== Rollback to 3.8.1"
git -C "$PLUGIN" show 3.8.1:includes/class-wpheka-gateway-moneris.php > "$HERE/.tmp/class-wpheka-gateway-moneris-3.8.1.php"
for v in live sandbox-no-logos; do
  php_check "$IN/rollback.php" save "$v"
  wp --skip-plugins=wc-moneris-payment-gateway eval-file "$IN/rollback.php" read "$v" "$IN/.tmp/class-wpheka-gateway-moneris-3.8.1.php" 2>&1 | grep -E '^(PASS|FAIL)\|' | sed 's/^/  /'
  ran "rollback.php ($v)"
done
echo "== Admin page in a browser"
run_node admin.js "admin.js"
echo "== Sandbox checkouts and refund (HPOS)"
run_node checkout.js "checkout.js (hpos)" hpos

echo "== Posts storage"
storage disable
php_check "$IN/stats.php" posts; ran "stats.php (posts)"
run_node checkout.js "checkout.js (posts)" posts

trap - EXIT
cleanup

echo "== debug.log"
NEW="$(docker exec "$CLI" sh -c "[ -f $LOG ] && tail -n +$((LOG_START + 1)) $LOG || true" | grep -i 'wc-moneris-payment-gateway' | grep -v 'tests/functional' || true)"
if [ -z "$NEW" ]; then record PASS "no new PHP errors from this plugin in debug.log"; else record FAIL "no new PHP errors from this plugin in debug.log -- $(echo "$NEW" | head -5 | tr '\n' ' ')"; fi

PASSED="$(grep -c '^PASS|' "$STATE/results")"
FAILED="$(grep -c '^FAIL|' "$STATE/results")"
echo
echo "== Result: $PASSED passed, $FAILED failed"
grep '^FAIL|' "$STATE/results" | sed 's/^/  /'
echo "Screenshots: $STATE/shots"
[ "$FAILED" = 0 ]
