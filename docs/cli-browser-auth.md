# WooPayments CLI browser authorization

WooPayments exposes a local-browser authorization flow for the WooPayments CLI.

## Endpoints

- `POST /wp-json/wc/v3/payments/cli/authorize`
- `POST /wp-json/wc/v3/payments/cli/token`

The authorize endpoint is intentionally unauthenticated so the CLI can discover support for the browser flow. Callback URLs must be local loopback callbacks (`http://127.0.0.1:<port>/...`, `http://localhost:<port>/...`, or `http://[::1]:<port>/...`). The WooPayments store can be remote; only the CLI callback listener must be local.

The browser approval page requires an authenticated WordPress user with `manage_woocommerce`. Approval creates a short-lived one-time code and redirects the browser back to the local CLI callback. The CLI exchanges that code for WooCommerce REST API credentials through the token endpoint. The consumer secret is only returned in the token response and is never included in browser redirect URLs.

Codes expire after five minutes and are single-use.
