# WordPress Playground previews

Every pull request gets two [WordPress Playground](https://wordpress.org/playground/) links in its "Test the build" comment. Playground runs WordPress in the visitor's browser, so the links need no site and no account.

- **Open with demo data** starts a store that looks connected. This directory is what makes that work.
- **Open unconnected** starts a fresh store on the WooPayments Connect screen.

The links are built in `.github/workflows/pr-build-live-branch.yml`.

## Why demo data

A site in a browser Playground has no public address, so WordPress.com cannot register it with Jetpack: "Connect your store" fails. A public link cannot carry a Jetpack token either. Without a connection WooPayments shows little beyond the Connect screen.

The preview plugin in `mu-plugins/` replaces the HTTP client WooPayments uses to reach its server (through the `wc_payments_http` filter) with one that reports a connection and answers from JSON files. No request leaves the site.

## What works and what does not

Works: Payments → Overview, Payouts, Transactions and Disputes lists, and the WooPayments settings screens.

Does not work:

- **Taking a payment.** The fixture account does not exist at Stripe, so the card form at checkout cannot load.
- **Detail pages.** Opening a single payout, transaction or dispute shows an empty or "not found" state, because there are no fixtures for individual items.
- **Anything that writes to the server**, such as changing the payout schedule or submitting dispute evidence.
- **Onboarding, webhooks and WooPay.**

The data never changes in response to what you do. Use the demo to look at screens, not to check server behaviour.

## How requests map to fixtures

A request is answered from the file named after it:

| Request | File |
|---|---|
| `GET accounts` | `fixtures/GET/accounts.json` |
| `GET deposits/overview-all` | `fixtures/GET/deposits/overview-all.json` |
| `POST accounts/store_setup` | `fixtures/POST/accounts/store_setup.json` |

Query parameters are ignored, so lists are not filtered, sorted or paginated.

A request with no file gets a `400` response with the code `wcpay_playground_no_demo_data`, and a line in the PHP error log: `WooPayments Playground preview: no fixture for GET some/route`. The UI then shows its normal error state, which makes a missing fixture visible.

Dates can be relative, so the data never looks stale:

| Value in the fixture | Becomes |
|---|---|
| `"@datetime:-2 days"` | `"2026-01-13 09:30:00"` (UTC) |
| `"@timestamp:-2 days"` | `1768296600` |
| `"@timestamp_ms:-2 days"` | `1768296600000` |

Anything PHP's `strtotime()` accepts works after the colon.

## Safety

The plugin does nothing unless the `WCPAY_PLAYGROUND_PREVIEW` constant is defined as `demo`; only the preview Blueprint defines it. It also stays out of the way on a site that has a Jetpack blog token, so fixtures cannot stand in for a real connection.

Nothing in this directory ships in the plugin zip.

## Changing the fixtures

When a pull request changes what a screen reads from the server, update the matching fixture in the same pull request: the preview loads the fixtures from the pull request's own build.

To try changes locally, run Playground with this directory mounted. It needs Node.js and nothing else:

```bash
npx @wp-playground/cli@3.1.64 server --login --blueprint=dev/playground/blueprint-local.json --mount-dir dev/playground/mu-plugins /wordpress/wp-content/mu-plugins
```

This installs WooCommerce and the latest `develop` build of WooPayments, and serves the site at <http://127.0.0.1:9400> (log in as `admin` / `password` if asked). Fixture edits apply on the next page load; account data is cached, so after changing `GET/accounts.json` run "Clear WooPayments account cache" under WooCommerce → Status → Tools. Missing fixtures are listed in `wp-content/debug.log`.
