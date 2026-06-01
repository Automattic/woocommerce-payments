# Autoresearch: Reduce WooPayments Admin Initial Bundle

## Objective
Minimize the JavaScript and CSS loaded unconditionally by the WooPayments admin entrypoint (`client/index.js`) while preserving WooCommerce Admin route registration, navigation metadata, i18n strings, and full feature coverage. The main expected win is to stop eagerly importing route/page containers that are only needed once a merchant navigates to the matching WooPayments admin route.

## Metrics
- **Primary**: `admin_initial_gzip_kb` (kb, lower is better) — gzip size of the initial WooPayments admin entry assets. In the current webpack config this is `dist/index.js` + `dist/index.css`; if synchronous initial chunks are introduced, include them too.
- **Secondary**:
  - `total_dist_gzip_kb` — gzip size of all built JS/CSS files in `dist/`, excluding source maps and RTL duplicate CSS.
  - `index_raw_kb` — raw, uncompressed size of `dist/index.js`.
  - `build_seconds` — wall-clock seconds for `NODE_ENV=production npm run build:client`.

## How to Run
`./autoresearch.sh` — runs a production client build and outputs structured `METRIC name=value` lines.

The benchmark command is intentionally the same build command requested by the user:

```bash
NODE_ENV=production npm run build:client
```

## Files in Scope
- `client/index.js` — primary admin entrypoint; registers WooCommerce Admin pages and onboarding tasks.
- `client/*/index.{js,jsx,ts,tsx}` route/page containers imported from `client/index.js` — candidates for dynamic imports via a shared lazy route wrapper.
- `client/reports/page-config.ts` and `client/reports/*` — reports route registration and report-only heavy dependencies such as DataViews and date filters.
- `client/disputes/**`, `client/payment-details/**`, `client/deposits/**`, `client/transactions/**`, `client/documents/**`, `client/card-readers/**`, `client/capital/**`, `client/onboarding/**`, `client/connect-account-page/**` — route containers currently pulled eagerly by the admin entrypoint.
- `includes/multi-currency/client/setup/**` and `includes/multi-currency/client/interface/components.js` — multi-currency setup route imported by the admin entrypoint.
- `client/settings/fraud-protection/advanced-settings/**` — fraud protection route currently imported by the admin entrypoint.
- `webpack/shared.js` — only if necessary to improve async chunking or keep dynamic route chunks loadable.
- `autoresearch.sh`, `autoresearch.checks.sh`, `autoresearch.md`, `autoresearch.ideas.md` — experiment harness and persistent notes.

## Off Limits
- Checkout/payment processing behavior.
- PHP payment intent/request code.
- Removing WooPayments Admin routes, hiding navigation entries, or reducing feature coverage.
- New runtime dependencies unless a large, clearly justified reduction requires them.

## Constraints
- Lazy-loaded routes must still render their previous default/named page components and receive the same route props (notably `query`).
- Preserve i18n for navigation labels/breadcrumbs and WooCommerce Admin navigation behavior.
- Keep route registration synchronous; only the route component body should load asynchronously.
- Prefer one reusable lazy-loading pattern applied consistently.
- Production build must succeed.
- `npm run lint:js` must pass before keeping code changes.
- Targeted Jest tests should be run manually when touching modules with relevant tests.
- Browser smoke for `/wp-admin/admin.php?page=wc-admin&path=/payments/overview` should be attempted for final kept changes if the local environment is available.

## Baseline Understanding
`client/index.js` currently imports every WooPayments admin page container eagerly before calling the `woocommerce_admin_pages_list` filter. This means heavyweight code for reports, dispute evidence, onboarding/KYC, multi-currency setup, capital, transactions, and detail pages is bundled into `dist/index.js` even when the current page only needs route registration metadata.

Webpack already emits async chunks for existing `React.lazy` usage inside reports. The production config does not enable initial `splitChunks`, so the initial WooPayments admin entry is effectively `dist/index.js` plus `dist/index.css` today.

## What's Been Tried
- Session setup only. No optimization experiments have been run yet.
