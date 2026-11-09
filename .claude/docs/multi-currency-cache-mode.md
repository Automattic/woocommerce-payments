# Multi-Currency Cache-Optimized Mode

**Last updated:** 2026-10-23

Reference for the cache-optimized multi-currency rendering mode. This mode allows full-page caching of product/catalog pages while still converting prices to the visitor's currency on the client side.

## Overview

In standard mode, WooPayments converts prices server-side via `FrontendPrices`, which depends on the WC session (preventing full-page caching). Cache-optimized mode replaces server-side conversion with:

1. **PHP**: Skeleton markup wrapping each price (cacheable — same for all visitors)
2. **JS**: Client-side conversion via REST API config fetch

## Component Map

| Component | File | Role |
|-----------|------|------|
| `AsyncPriceRenderer` | `includes/multi-currency/AsyncPriceRenderer.php` | Hooks into `wc_price`, wraps prices with skeleton markup; enqueues JS |
| `WCPayAsyncPriceRenderer` | `includes/multi-currency/client/async-renderer/index.ts` | Fetches currency config, converts skeletons to formatted prices, shows the geolocation notice |
| Public REST endpoint | `includes/multi-currency/RestController.php` (`GET /wc/v3/payments/multi-currency/public/config`) | Returns currency rates, formatting config, selected currency, geolocation notice (names and dismissal ID); `Cache-Control: private, max-age=300` |
| Geolocation notice template | `includes/multi-currency/MultiCurrency.php` (`display_geolocation_currency_update_notice()`) | Prints a `<template>` of the "We noticed you're visiting from…" notice; see [Geolocation Notice](#geolocation-notice) |

## Gating Conditions

`AsyncPriceRenderer::init_hooks()` activates only when ALL of the following are true:

1. `is_cache_optimized_mode()` — feature flag + rendering mode option is set to `'cache'`
2. Not admin, not cron, not admin API request
3. `! has_active_session()` — no WC session → use async renderer (FrontendPrices handles sessions)
4. Not a Store API request (`should_use_async_rendering()`)
5. `is_using_auto_currency_switching()`, checked in `MultiCurrency::init()` — without it, session-less visitors always see the default currency

**URL currency switch (`?currency=`)**: When a pending currency switch exists (`has_pending_currency_switch()`), `MultiCurrency::init()` skips the async renderer in favor of FrontendPrices, which creates a session for that request.

## Skeleton Markup

```html
<span class="woocommerce-Price-amount amount wcpay-async-price"
      data-wcpay-price="19.99"
      data-wcpay-price-type="product">
  <bdi class="wcpay-price-skeleton"></bdi>
  <span class="screen-reader-text wcpay-price-placeholder">
    <span class="woocommerce-Price-amount amount">
      <span class="woocommerce-Price-currencySymbol">$</span>19.99
    </span>
  </span>
</span>
```

- The wrapper reuses `woocommerce-Price-amount amount` classes so theme CSS selectors like `.price > .woocommerce-Price-amount` still work. No extra nesting level.
- `<bdi class="wcpay-price-skeleton">` matches the `<bdi>` element `wc_price()` produces.
- The `screen-reader-text` placeholder contains the original WC-formatted price (default currency) for crawlers and screen readers before JS loads. It does not defeat page caching since all visitors get the same default-currency price.
- `data-wcpay-price` — raw numeric price in default currency, dot-notation (from PHP float)
- `data-wcpay-price-type` — one of `product`, `shipping`, `tax`, `coupon`, `exchange_rate` (default: `product`). Customizable via `wcpay_multi_currency_async_price_type` filter.

After conversion, JS removes the skeleton `<bdi>` and placeholder, appends a new `<bdi>` with the converted price (including `<span class="woocommerce-Price-currencySymbol">`), and adds `.wcpay-price-converted` to the wrapper:

```html
<span class="woocommerce-Price-amount amount wcpay-async-price wcpay-price-converted"
      data-wcpay-price="19.99"
      data-wcpay-price-type="product">
  <bdi>18,69&nbsp;<span class="woocommerce-Price-currencySymbol">€</span></bdi>
</span>
```

## Price Type Classification

| Type | Rounding | Charm pricing |
|------|----------|---------------|
| `product` | Yes (rounding + CEIL) | Yes (if `charm_only_products` or always) |
| `shipping` | Yes (rounding + CEIL) | Only if `charm_only_products === false` |
| `tax`, `coupon`, `exchange_rate`, unknown | No | No |

This mirrors PHP's `MultiCurrency::get_price()` (products/shipping) and `get_adjusted_price()` (tax/coupon). **Keep JS and PHP in sync** — changes to rounding or charm logic must be applied to both.

## `wcpayAsyncPriceConfig` JS Global

Injected via `wp_localize_script`. Contains:
- `apiUrl` — REST endpoint URL
- `defaultCurrency` — store default currency for error-state fallback (symbol, decimals, separators, position)

**Critical:** `defaultCurrency.symbol` must be decoded before injection. `get_woocommerce_currency_symbol()` returns HTML entities (e.g. `&#36;`, `&euro;`). Always use `html_entity_decode()`:

```php
'symbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
```

Skipping this causes JS to render the raw entity string (e.g. `&#36;5.00`) when `textContent` is set.

## `formatPrice` / `buildPriceBdi` vs `formatCurrency`

The storefront async renderer uses its own `formatPrice()` and `buildPriceBdi()` methods. It does **not** use `formatCurrency` from `includes/multi-currency/client/utils/currency/index.js`.

**Why:** `formatCurrency` depends on `wcpaySettings` (admin-only global) and heavy packages (`@woocommerce/currency`, `lodash`) — both unavailable on the storefront.

- `formatPrice()` returns only the formatted number string (no symbol), e.g. `"18,69"`. Uses `Decimal.toFixed()` which always produces dot-notation regardless of locale, so it is safe to split on `.` for decimal separation.
- `buildPriceBdi()` assembles a `<bdi>` element with the symbol (`<span class="woocommerce-Price-currencySymbol">`) and number in the correct position, mirroring how PHP's `wc_price()` uses `sprintf($format, $symbol, $number)`. This avoids fragile string-slicing heuristics.

## Error State

When the config fetch fails, `showErrorState()` falls back in order:
1. Format the raw `data-wcpay-price` using `wcpayAsyncPriceConfig.defaultCurrency` → show default-currency price
2. If no `defaultCurrency` or formatting fails → replace skeleton with `—` (em dash, `.wcpay-price-error`)

The `defaultCurrency` data is store-wide (same for all visitors) so it is safe to include in the cached page via `wp_localize_script`.

## Geolocation Notice

The "We noticed you're visiting from…" notice names the visitor's country, so the server can't print it into a cached page. When `should_use_async_rendering()` is true at `wp_footer`, `display_geolocation_currency_update_notice()` prints a template instead, and the JS fills it in from the config:

```html
<template id="wcpay-geolocation-notice">
  <p class="woocommerce-store-notice demo_store" data-notice-id="" style="display:none;">
    We noticed you're visiting from <span data-wcpay-notice-country></span>.
    We've updated our prices to <span data-wcpay-notice-currency></span> for your shopping convenience.
    <a href="?currency=USD">Use United States (US) dollar instead.</a>
    <a href="#" class="woocommerce-store-notice__dismiss-link">Dismiss</a>
  </p>
</template>
```

- **Why `<template>`:** WooCommerce's `woocommerce.js` shows every `.woocommerce-store-notice` on DOM ready. A `<template>`'s content isn't in the DOM, so the script can't show an empty notice.
- **One `wp_footer` callback for both modes:** `update_selected_currency_by_geolocation()` registers it before the async early return, so unhooking `display_geolocation_currency_update_notice` hides the notice in both modes.
- **Config field:** `get_public_config()` returns `geolocation_notice: { country_name, currency_name, id }`, or `null` unless auto-switching is on and the visitor sees their geolocated currency instead of the store currency. It decodes only the returned names with `WC_Payments_Utils::decode_html_entities()`, because the JS sets them with `textContent`.
- **Dismissal ID:** `id` is `md5( $message ) . '2'`, hashed from the undecoded message. Both modes build the notice with `get_geolocation_notice()`, so the IDs match. Decoding before hashing would break this.
- **Dismissal:** WooCommerce's script binds on DOM ready, usually before the config arrives, so the JS handles the dismiss link itself. It reads and sets WooCommerce's `store_notice<id>=hidden` cookie, so a dismissal holds on later cached pages and after the visitor gets a session.
- **Filters:** `wcpay_multi_currency_override_notice_country` and `wcpay_multi_currency_override_notice_currency_name` also run in the `/public/config` REST request, where WooCommerce doesn't load the cart, customer or session. A callback that returns `null` yields an empty name.

Known gaps: the notice appears once the config arrives, not on page load. On multilingual sites the REST request may build the names in a different language from the page. The notice can then mix languages, and its `id` can differ from the server-rendered one, so a dismissal may not carry over.
