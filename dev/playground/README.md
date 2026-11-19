# WordPress Playground previews

Every pull request gets [WordPress Playground](https://wordpress.org/playground/) links in its "Test the build" comment. Playground runs WordPress in the visitor's browser, so the links need no site of your own.

- **Open with demo data** starts a store that looks connected and reads everything from fixture files.
- **Open unconnected** starts a fresh store on the WooPayments Connect screen.

The links are built in `.github/workflows/pr-build-live-branch.yml`.

This directory provides the demo data, and a third mode that is only run locally: a store that asks for the connection of a sandbox site you already have, then talks to the real server. See [Connecting a sandbox](#connecting-a-sandbox) for why it has no link.

## Why the previews need help

A site in a browser Playground has no public address, so WordPress.com cannot register it with Jetpack: "Connect your store" fails. A public link cannot carry a Jetpack token either. Without a connection WooPayments shows little beyond the Connect screen.

The preview plugin in `mu-plugins/` gets around this in two ways, chosen by the `WCPAY_PLAYGROUND_PREVIEW` constant the Blueprint defines:

| Value | What the plugin does |
|---|---|
| `demo` | Replaces the HTTP client WooPayments uses to reach its server (through the `wc_payments_http` filter) with one that reports a connection and answers from JSON files. No request leaves the site. |
| `connect` | Adds a "Sandbox connection" page where the visitor pastes an existing connection. WooPayments then talks to the real server as that sandbox site. |

## Demo data: what works and what does not

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

## Connecting a sandbox

This mode runs locally, from code you have checked out:

```bash
npx @wp-playground/cli@3.1.64 server --login --blueprint=dev/playground/blueprint-local-connect.json --mount-dir dev/playground/mu-plugins /wordpress/wp-content/mu-plugins
```

It has no link in the pull request comment on purpose. Whatever code runs in the preview can read the connection that is pasted in, and a link cannot promise whose code that is: Playground's artifact proxy picks a build by branch name, so it serves the newest build of any pull request whose branch has that name, forks included.

The "Sandbox connection" page shows a WP-CLI command to run on a sandbox site that is already connected to WooPayments. It prints one line of JSON with that site's blog ID and Jetpack tokens; paste it into the page and the preview uses that connection. A "Remove the connection" button takes it out again.

With a connection, the preview shows the sandbox account's real data and can take test payments at checkout. WooPayments is kept in test mode whatever account is behind the connection.

What it cannot do:

- **Receive webhooks.** Nothing on the internet can reach the preview, so anything that arrives by webhook, such as dispute updates, never shows up.
- **Onboard a new account.** It reuses a connection; it cannot create one.

Four things in the plugin make this work, and should stay:

- **Jetpack sync is off.** The preview shares a blog ID with the sandbox site. If it synced, WordPress.com would see a second URL for that blog and could put the sandbox site into Jetpack safe mode.
- **Jetpack identity-crisis handling is off.** The preview's URL never matches the one WordPress.com has for that blog, and the mismatch it would report back puts the preview itself into safe mode.
- **Two request headers are dropped**, `Idempotency-Key` and `X-Request-Initiated`. In a browser Playground the browser makes PHP's HTTP requests, and WordPress.com does not allow those two headers across origins, so every request carrying them would fail. The local command does not need this, but it keeps the mode usable in a browser Playground. The server works without them. Without an idempotency key the API client does not retry, so a request that fails in the preview fails once.
- **A placeholder user may be created.** Jetpack only accepts a user token for the user ID it was issued to, so if the sandbox site's connection owner is not user 1, the preview creates a user with that ID and no role.

**Only paste a connection from a sandbox site, and only into a preview running code you trust.** The local command installs the latest `develop` build of WooPayments and mounts the preview plugin from your checkout. The connection stays in that Playground site until it is removed or the site is deleted.

## Safety

The plugin does nothing unless `WCPAY_PLAYGROUND_PREVIEW` is defined as `demo` or `connect`; only the Blueprints define it, and the pull request links only ever use `demo`. In demo mode it also stays out of the way on a site that has a Jetpack blog token, so fixtures cannot stand in for a real connection.

Nothing in this directory ships in the plugin zip.

## Changing the fixtures

When a pull request changes what a screen reads from the server, update the matching fixture in the same pull request: the preview loads the fixtures from the pull request's own build. `tests/unit/dev/test-class-wcpay-playground-demo-http.php` requests every fixture in this directory, so one that is not valid JSON fails CI.

To try fixture changes locally, run Playground with this directory mounted. It needs Node.js and nothing else:

```bash
npx @wp-playground/cli@3.1.64 server --login --blueprint=dev/playground/blueprint-local.json --mount-dir dev/playground/mu-plugins /wordpress/wp-content/mu-plugins
```

This installs WooCommerce and the latest `develop` build of WooPayments, and serves the site at <http://127.0.0.1:9400> (log in as `admin` / `password` if asked). Fixture edits apply on the next page load; account data is cached, so after changing `GET/accounts.json` run "Clear WooPayments account cache" under WooCommerce → Status → Tools. Missing fixtures are listed in `wp-content/debug.log`.
