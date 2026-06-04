# WSN Profile Sync — Integration Testing Runbook

**Last updated:** 2026-06-04

How to integration-test the WSN Profile sync end-to-end between a local WCPay merchant site and a WooPay sandbox. Written for the WooPay-side session validating the just-landed `/wsn/v1/merchants/{blog_id}/profile` receiver against a real WCPay push.

For architecture / wire shape see [wsn-profile-sync-architecture.md](./wsn-profile-sync-architecture.md). This runbook is operational.

## Prerequisites

| | Required state |
|---|---|
| **WCPay (merchant)** | Local Docker environment running (`npm run up` brings it up at `http://localhost:8082`). Jetpack connected (a real connection — the signer needs a real blog token). Both feature flags enabled (see below). |
| **WooPay (receiver)** | Sandbox URL reachable from the WCPay container. Receiver feature flag `woopay_wsn_merchant_profile_endpoint_enabled` ON. |
| **Network** | Outbound HTTPS from the WCPay container to the WooPay sandbox host. |

## WCPay-side setup

### 1. Point WCPay at the WooPay sandbox

Edit your WCPay Docker `wp-config.php` (mounted at `docker/wordpress/wp-config.php`):

```php
define( 'PLATFORM_CHECKOUT_HOST', 'https://your-woopay-sandbox.example.com' );
```

Restart the WCPay container so the constant is picked up:

```bash
docker compose restart wordpress
```

Verify:

```bash
docker compose exec -u www-data wordpress wp eval \
  'echo WCPay\WooPay\WooPay_Utilities::get_woopay_url();'
# Expected: https://your-woopay-sandbox.example.com
```

### 2. Enable both feature flags

```bash
docker compose exec -u www-data wordpress wp option update _wcpay_feature_wsn_hub 1
docker compose exec -u www-data wordpress wp option update _wcpay_feature_wsn_profile_emitter 1
```

Verify the Hub admin page is reachable: open `http://localhost:8082/wp-admin/admin.php?page=wcpay-shopping-network` (creds: `admin` / `admin`).

### 3. Enable WSN (`wcpay_wsn_enabled`)

In the Hub Overview tab, click **Enable Woo Shopping Network**. Or via wp-cli:

```bash
docker compose exec -u www-data wordpress wp option update wcpay_wsn_enabled 1
```

## Triggering a push

Three ways, ordered by determinism:

### A. Profile-tab Retry button (preferred — exercises the full UI surface)

1. Open the Hub → **Profile** tab.
2. Find the sync-state badge at the top.
3. Click **Retry sync**.

The badge transitions to `Syncing…` immediately; after ~60s (debounce window) the page auto-refreshes and the badge shows `Last synced just now`.

### B. WP-CLI direct trigger (skips the UI, fastest for headless tests)

```bash
docker compose exec -u www-data wordpress wp eval \
  'do_action("wcpay_wsn_profile_force_resync");'
```

Same outcome as the Retry button — schedules an AS action at `time()` (no debounce). Bypasses the REST throttle.

### C. REST endpoint direct (matches what the UI does)

```bash
# Requires a valid nonce + admin session cookie — easiest via the UI.
curl -X POST http://localhost:8082/wp-json/wc/v3/payments/wsn/profile-resync \
  -H "X-WP-Nonce: <nonce>" \
  --cookie "wordpress_logged_in_...=..."
```

Returns `202 Accepted` with `{ "status": "scheduled", "rescheduled_at": <ts> }` on success, `429` + `Retry-After` header if throttled, `503` if the emitter sub-flag is off.

### D. Natural trigger (slowest — proves the change-driven path)

Edit any Profile field in the Hub UI and save. The push fires 60s later via the standard debounce path. Use this only when validating the change-driven trigger; A/B/C are better for everything else.

## Verifying the push landed

### On the WCPay side (what was pushed)

```bash
docker compose exec -u www-data wordpress wp eval \
  'echo WSN_Profile_Emitter::get_last_synced_version();'
# Returns a 64-char sha256.
```

```bash
docker compose exec -u www-data wordpress wp eval \
  'echo WSN_Profile_Emitter::get_last_synced_time();'
# Returns a unix timestamp (the wall-clock of the most recent successful push).
```

```bash
docker compose exec -u www-data wordpress wp eval \
  'print_r(WSN_Profile_Emitter::get_last_error());'
# Returns null on success, or { message, timestamp } on the most recent failure.
```

Or check the same surface via REST:

```bash
curl http://localhost:8082/wp-json/wc/v3/payments/wsn/settings \
  -H "X-WP-Nonce: <nonce>" \
  --cookie "wordpress_logged_in_...=..." \
  | jq .sync
```

### On the WooPay side (what was received)

Query the receiver's DB:

```sql
SELECT blog_id, host, payload_version, client_updated_at, last_seen_at
FROM wp_woopay_wsn_merchant_profile
WHERE blog_id = <merchant blog_id>;
```

The `payload_version` column MUST equal the WCPay-side `get_last_synced_version()` output. If they match, the push round-tripped end-to-end.

To resolve the WCPay blog_id from the merchant side:

```bash
docker compose exec -u www-data wordpress wp eval \
  'echo (int) Jetpack_Options::get_option("id");'
```

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `wsn_profile_last_error` shows `401 unauthorized` from WooPay | Jetpack not connected, or token expired | Reconnect Jetpack via WP admin → Jetpack settings; verify with `wp eval 'echo (int) Jetpack_Options::get_option("id");'` |
| `wsn_profile_last_error` shows `403 forbidden` from WooPay | WooPay receiver flag is off | Flip `woopay_wsn_merchant_profile_endpoint_enabled` on WooPay side |
| `wsn_profile_last_error` shows `404 not found` from WooPay | Wrong host in `PLATFORM_CHECKOUT_HOST`, or route not registered on WooPay side | Verify the URL: `wp eval 'echo WCPay\WooPay\WooPay_Utilities::get_woopay_url();'` should match the WooPay sandbox; confirm the route exists by hitting it directly |
| `wsn_profile_last_error` shows `signature_mismatch` or similar | Local Jetpack signs with `DEV_BLOG_TOKEN_SECRET` but sandbox expects production token | Set `wcpay_woopay_use_blog_token` filter to match WooPay's expected env |
| Retry button returns `429` immediately | Throttle transient is set (1-per-minute) | Wait or `wp transient delete wsn_profile_resync_throttle` |
| Retry button returns `503` | Profile emitter sub-flag is off | `wp option update _wcpay_feature_wsn_profile_emitter 1` |
| Badge stays in `Syncing…` indefinitely after Retry | Action Scheduler isn't running | Check `wp action-scheduler run --hooks=wcpay_wsn_profile_push`; verify cron isn't disabled |
| `last_synced` updates but WooPay DB row doesn't appear | Push succeeded against the wrong host (e.g. prod) | Confirm `PLATFORM_CHECKOUT_HOST` is set and the container restarted to pick it up |

## Common verification flow (one-line summary)

```bash
# Setup (once per session)
docker compose exec -u www-data wordpress wp option update _wcpay_feature_wsn_hub 1
docker compose exec -u www-data wordpress wp option update _wcpay_feature_wsn_profile_emitter 1
docker compose exec -u www-data wordpress wp option update wcpay_wsn_enabled 1

# Trigger + verify (per test cycle)
docker compose exec -u www-data wordpress wp eval 'do_action("wcpay_wsn_profile_force_resync");'
sleep 65  # debounce window
docker compose exec -u www-data wordpress wp eval 'echo WSN_Profile_Emitter::get_last_synced_version();'
# → Compare against WooPay's wp_woopay_wsn_merchant_profile.payload_version
```

## References

- Architecture: [wsn-profile-sync-architecture.md](./wsn-profile-sync-architecture.md)
- WCPay-side transport: [`includes/wsn/class-wsn-profile-transport.php`](../../includes/wsn/class-wsn-profile-transport.php)
- WCPay-side emitter: [`includes/wsn/class-wsn-profile-emitter.php`](../../includes/wsn/class-wsn-profile-emitter.php)
- Production existence-proof for the transport pattern: [`includes/woopay/class-woopay-session.php`](../../includes/woopay/class-woopay-session.php) `ajax_init_woopay()`
- Resync REST endpoint: `POST /wc/v3/payments/wsn/profile-resync` ([`includes/admin/class-wc-rest-payments-wsn-settings-controller.php`](../../includes/admin/class-wc-rest-payments-wsn-settings-controller.php))
