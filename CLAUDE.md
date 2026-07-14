# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WC Moneris Payment Gateway (v3.7.0) — a WooCommerce credit card payment gateway for Moneris (Canada's payment processor), by WPHEKA. Text domain: `wpheka-gateway-moneris`. The plugin skips loading if the Pro version of the gateway is already active on the site.

## Build Commands

```bash
npm install
npm start          # dev build with hot reload (wp-scripts)
npm run build      # production build
npm run make-pot   # regenerate languages/wpheka-gateway-moneris.pot
```

- Entry: `resources/js/frontend/payment-block.js`
- Output: `assets/js/block/frontend/payment-block.js`
- Uses `@wordpress/scripts` webpack config extended by `webpack.config.js` with `@woocommerce/dependency-extraction-webpack-plugin`
- Requires Node ^20.11.1 / npm ^10.2.4

Moneris API library (Composer, PSR-4 `wpheka\Moneris\` namespace):
```bash
cd includes/wpheka-moneris-api && composer install
```
The `vendor/` directory is committed; only run composer if changing the library.

## Architecture

- `wc-moneris-payment-gateway.php` — defines `WPHEKA_MONERIS_*` constants, admin notices (missing cURL/WC, version checks, Pro conflict), HPOS + cart/checkout blocks compatibility declarations, blocks payment method registration, and the `WPHEKA_Moneris` singleton (defined inline inside `wpheka_gateway_moneris_init` on `plugins_loaded`). The singleton loads includes and registers the gateway via `woocommerce_payment_gateways`.
- `includes/class-wpheka-gateway-moneris.php` — `WPHEKA_Gateway_Moneris extends WC_Payment_Gateway_CC`. Gateway id `moneris`. Handles settings (sandbox, store_id, api_token, preferred cards, logging), `process_payment()`, `process_refund()` (same-day refunds are voided via purchase correction), and order meta via HPOS-aware helpers (`get_order_meta_data`/`update_order_meta_data` use `OrderUtil` to branch between HPOS and legacy post meta).
- `includes/wpheka-moneris-api/src/` — `Gateway.php` (wrapper) and `mpgClasses.php` (Moneris mpg API classes for HTTPS posts to Moneris).
- `includes/class-wpheka-moneris-logger.php` — logging via WC logger, gated by the `wpheka_moneris_logging` filter/setting.
- `includes/blocks/class-wpheka-gateway-moneris-blocks-support.php` — `AbstractPaymentMethodType` implementation for WooCommerce Blocks checkout (block name `monerisgateway/moneris_direct`).
- `includes/admin/` — deactivation feedback and donation notice classes (admin only).
- `resources/js/frontend/` — React source for the blocks checkout card form (`cleave.js`, `react-hook-form`).

## Conventions

### PHPCS — run after every PHP file change

After modifying any PHP file, immediately run PHPCS on that file and fix the reported issues:

```bash
~/.composer/vendor/bin/phpcs --standard=WordPress path/to/file.php
```

`phpcs` lives in `~/.composer/vendor/bin/` and may not be on PATH in non-interactive shells — always use the full path.

**Exception:** do NOT run PHPCS on `includes/wpheka-moneris-api/src/mpgClasses.php` — it is vendored third-party code (see "Vendored Moneris Library" below) and is not held to WordPress coding standards.

- Guard every PHP file with `if (!defined('ABSPATH')) { exit; }`
- Prefix functions/hooks/constants with `wpheka_moneris` / `WPHEKA_MONERIS`
- Escape output (`esc_html__`, `esc_attr`, `esc_url`); translations use text domain `wpheka-gateway-moneris`
- Order meta access must stay HPOS-compatible (use the existing helper methods, not direct `get_post_meta`)
- Minimum supported PHP is 5.6 — avoid newer-PHP-only syntax in plugin PHP code

## Vendored Moneris Library

`includes/wpheka-moneris-api/src/mpgClasses.php` is a fork of Moneris's official library ([Moneris/Moneris-Gateway-API-PHP](https://github.com/Moneris/Moneris-Gateway-API-PHP), `mpgClasses.php`). Treat it as vendor code: do not refactor, restyle, or run PHPCS on it. `src/Gateway.php` is NOT from that repo — it is WPHEKA's own wrapper and follows normal plugin standards.

The fork carries exactly two deliberate local patches. **Never overwrite this file with the upstream version without re-applying them:**

1. `namespace wpheka\Moneris;` at the top of the file (prevents class collisions with other plugins bundling the same library).
2. SSL verification enabled in `httpsPost`: `CURLOPT_SSL_VERIFYPEER => true` and `CURLOPT_SSL_VERIFYHOST => 2`. Upstream ships with verification **disabled** — syncing it verbatim would reintroduce a MITM vulnerability on every payment request. (`curl_close()` is also uncommented locally.)

Everything else (hosts, endpoints, API version) should match upstream. The local copy is an older upstream snapshot with reformatted whitespace, so raw diffs look larger than they are — use `diff -wB` when comparing against upstream.

## Git

- **Never add `Co-Authored-By` lines to commit messages.** Commits should use the developer's git account only.

## Releasing — new version checklist

Work through these in order for every release:

1. **Decide the version** (semver): bug fixes only → PATCH; new features → MINOR; breaking changes → MAJOR.
2. **Check latest WP/WC versions** and test against them before claiming compatibility:
   ```bash
   curl -s "https://api.wordpress.org/core/version-check/1.7/" | python3 -c "import json,sys; print(json.load(sys.stdin)['offers'][0]['current'])"
   curl -s "https://api.wordpress.org/plugins/info/1.0/woocommerce.json" | python3 -c "import json,sys; print(json.load(sys.stdin)['version'])"
   ```
   Update `Tested up to:` in `readme.txt` AND the main file, plus `WC tested up to:` in the main file. Only bump these after actually testing on those versions (local install should match).
3. **Bump the version in FOUR places together** (they must stay in sync):
   - `wc-moneris-payment-gateway.php` — `* Version:` header
   - `wc-moneris-payment-gateway.php` — `WPHEKA_MONERIS_VERSION` constant
   - `package.json` — `"version"`
   - `readme.txt` — `Stable tag:`
4. **Prepend a changelog entry** in `readme.txt` under `== Changelog ==`, newest first, format:
   ```
   YYYY-MM-DD - version X.X.X
   * Fix - ...
   * Add - ...
   * Enhancement - ...
   * Update - ...
   ```
   User-facing changes only; group minor related fixes.
5. **Regenerate the translation template** if any PHP strings changed: `npm run make-pot` (verify new strings actually appear in `languages/wpheka-gateway-moneris.pot`).
6. **Production build**: `npm install && npm run build` — the compiled `assets/js/block/frontend/payment-block.js` + `.asset.php` are committed to the repo.
7. **Quality gates**: `php -l` on every changed PHP file; PHPCS per the rule above (never on `mpgClasses.php`); confirm no accidental edits to the vendored library beyond its two documented patches.
8. **Functional verification (sandbox mode, test creds `store5`/`yesguy`, card 4242 4242 4242 4242)**:
   - Gateway registers: `wp eval 'print_r(array_keys(WC()->payment_gateways()->get_available_payment_gateways()));'`
   - One purchase on classic checkout AND one on block checkout (test page: `/block-checkout-test/`)
   - With logging enabled, confirm the request XML in WooCommerce → Status → Logs shows the PAN masked and `api_token`/`expdate` redacted
   - A taxed order shows Tax 1/2/3 populated in the Moneris test Merchant Resource Center
   - One refund on a previous-day order with HPOS enabled
9. **User sign-off before committing** — never commit/tag a release without explicit approval of the test results.

## Distribution

The plugin ships via wordpress.org (the `Stable tag:` in `readme.txt` drives the release).
