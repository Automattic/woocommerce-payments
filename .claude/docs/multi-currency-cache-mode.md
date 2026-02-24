# Multi-Currency Cache-Optimized Mode

**Last updated:** 2026-02-24

Reference for the cache-optimized multi-currency rendering mode. This mode allows full-page caching of product/catalog pages while still converting prices to the visitor's currency on the client side.

## Overview

In standard mode, WooPayments converts prices server-side via `FrontendPrices`, which depends on the WC session (preventing full-page caching). Cache-optimized mode replaces server-side conversion with:

1. **PHP**: Skeleton markup wrapping each price (cacheable — same for all visitors)
2. **JS**: Client-side conversion via REST API config fetch

## Component Map

| Component | File | Role |
|-----------|------|------|
| `AsyncPriceRenderer` | `includes/multi-currency/AsyncPriceRenderer.php` | Hooks into `wc_price`, wraps prices with skeleton markup; enqueues JS |
| `WCPayAsyncPriceRenderer` | `includes/multi-currency/client/async-renderer/index.js` | Fetches currency config, converts skeletons to formatted prices |
| Public REST endpoint | `includes/multi-currency/RestController.php` (`GET /wc/v3/payments/multi-currency/public/config`) | Returns currency rates, formatting config, selected currency; `Cache-Control: private, max-age=300` |

## Gating Conditions

`AsyncPriceRenderer::init_hooks()` activates only when ALL of the following are true:

1. `is_cache_optimized_mode()` — feature flag + rendering mode option is set to `'cache'`
2. Not admin, not cron, not admin API request
3. `! has_active_session()` — no WC session → use async renderer (FrontendPrices handles sessions)
4. `is_using_auto_currency_switching()` — without auto-switching, session-less visitors always see default currency; skeletons add JS latency with zero benefit

**URL currency switch (`?currency=`)**: When a pending currency switch exists (`has_pending_currency_switch()`), `MultiCurrency::init()` skips the async renderer in favor of FrontendPrices, which creates a session for that request.

## Skeleton Markup

```html
<span class="wcpay-async-price"
      data-wcpay-price="19.99"
      data-wcpay-price-type="product">
  <span class="wcpay-price-skeleton"></span>
</span>
```

- `data-wcpay-price` — raw numeric price in default currency, dot-notation (from PHP float)
- `data-wcpay-price-type` — one of `product`, `shipping`, `tax`, `coupon`, `exchange_rate` (default: `product`). Customizable via `wcpay_multi_currency_async_price_type` filter.

After conversion, JS adds `.wcpay-price-converted` and injects a `.woocommerce-Price-amount.amount` span.

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

## `formatPrice` vs `formatCurrency`

The storefront async renderer uses its own `formatPrice()` method. It does **not** use `formatCurrency` from `includes/multi-currency/client/utils/currency/index.js`.

**Why:** `formatCurrency` depends on `wcpaySettings` (admin-only global) and heavy packages (`@woocommerce/currency`, `lodash`) — both unavailable on the storefront.

`formatPrice()` uses `Decimal.toFixed()` which always produces dot-notation regardless of locale, so it is safe to split on `.` for decimal separation.

## Error State

When the config fetch fails, `showErrorState()` falls back in order:
1. Format the raw `data-wcpay-price` using `wcpayAsyncPriceConfig.defaultCurrency` → show default-currency price
2. If no `defaultCurrency` or formatting fails → replace skeleton with `—` (em dash, `.wcpay-price-error`)

The `defaultCurrency` data is store-wide (same for all visitors) so it is safe to include in the cached page via `wp_localize_script`.
