---
name: browse
description: Check a WooPayments change by hand in a real browser on the local dev site, one command at a time, with `pnpm run browse`. Use to see a checkout, payment or admin change working without writing a test. Not for E2E specs (see e2e-testing).
metadata:
  version: "1.0.0"
---

# Checking a change with `pnpm run browse`

`pnpm run browse` drives one Chrome window on this checkout's dev site (`pnpm run up`, port from `.env`). The browser stays open between commands, so each command carries on from the page the last one left. WooPayments commands call the E2E helpers in `tests/e2e/utils/`; any other command goes to [playwright-cli](https://playwright.dev/docs/getting-started-cli) in the same browser.

Nothing here asserts anything. When a check is worth keeping, write an E2E spec instead.

## Before you start

1. The dev site is running and `pnpm run watch` is building your changes.
2. `pnpm run browse open` opens Chrome logged in as `admin`. Add `--headless` to keep the window hidden.
3. `pnpm run browse status` shows the WooPayments account. If it reports an error, the site can't reach the WooPayments server: check the dev tools API redirect. `pay` refuses to run unless the checkout is in test mode.
4. `pnpm run browse seed` once per site adds the `customer` user, the `free` coupon and a classic checkout page at `/checkout-classic`. It never changes existing data.

## Commands

| Command | What it does |
|---------|--------------|
| `open [--headless]`, `close` | Start or stop the browser |
| `status` | Account ID, country, status, test and dev mode |
| `login [admin\|customer]`, `logout` | Switch user; there's one user at a time |
| `checkout [product] [--classic]` | Empty the cart, add a product (ID or slug, default `beanie`) and fill the billing address as the current user. `--classic` uses the classic checkout page instead of the site's checkout page |
| `card <name>` | Fill a test card from `config.cards`, such as `basic`, `3ds2` or `declined`. `card`, `pay` and `confirm-3ds` work on either checkout |
| `pay` | Place the order and print the order number, the error, or that a 3DS challenge is open |
| `confirm-3ds [fail]` | Complete or fail the open 3DS challenge |
| `settings`, `wc-settings [tab]`, `transactions` | Admin pages |
| `order <id>`, `payment-details <intent id>` | Admin order and payment details |
| `enable-methods '["Klarna"]'`, `disable-methods` | Payment method checkboxes, by label |
| `capture-later on\|off` | Manual capture. Turning it on also turns off the methods that don't support it, and turning it off doesn't bring them back |
| `devtools card-testing\|disconnected on\|off` | Dev tools settings |
| `api <method> <path> [data]` | A REST request as the logged-in user, such as `api GET /wc/v3/orders/123` |
| `snapshot`, `click <ref>`, `type`, `press`, `screenshot`, `goto <url>` | playwright-cli; see `pnpm exec playwright-cli --help` |

Arguments are read as JSON when they are JSON. `snapshot` prints the page as an accessibility tree with a `ref` for each element, which `click` and `fill` take. Snapshots and screenshots go to `tests/e2e/artifacts/browse/`.

## Example: a 3DS payment as a guest

```bash
pnpm run browse logout
pnpm run browse checkout
pnpm run browse card 3ds2
pnpm run browse pay           # 3DS challenge open
pnpm run browse confirm-3ds   # Order #123 received.
pnpm run browse login admin
pnpm run browse order 123
pnpm run browse snapshot
```

## Limits

- Commands change the dev site like a person would. Put back any setting you change.
