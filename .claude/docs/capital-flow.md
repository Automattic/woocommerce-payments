# Stripe Capital Flow — Reference

**Last updated:** 2026-04-15

This documents how Stripe Capital loan offers reach a WooPayments merchant, how they are accepted, and how the Capital Loans admin page becomes available. Read this when working on anything under `includes/core/server/request/class-get-account-capital-link.php`, `includes/admin/class-wc-rest-payments-capital-controller.php`, `client/capital/`, or the `wcpay-loan-offer` query param.

## Key Fact: No In-Plugin Accept UI

WooPayments does **not** render its own "accept offer" screen. Acceptance happens entirely on a **Stripe-hosted dashboard page**. The plugin only:

1. Generates the hosted link (via a transact endpoint)
2. Redirects the merchant there
3. Displays the loan *after* it has been disbursed

This is why searching the React codebase for an "accept offer" component turns up nothing — it does not exist.

## Entry Points

### 1. Inbox "loan offer" note (production path)

When Stripe makes an offer available, transact triggers an admin inbox note that links back to `/wp-admin/admin.php?page=wc-admin&wcpay-loan-offer`.

### 2. `?wcpay-loan-offer` query param (also used for testing)

Any admin-authenticated visit to a wp-admin URL containing `wcpay-loan-offer` triggers the redirect. Handler:

```
includes/class-wc-payments-account.php:810
    if ( isset( $_GET['wcpay-loan-offer'] ) ) {
        $this->redirect_service->redirect_to_capital_view_offer_page();
    }
```

The redirect service generates a Stripe-hosted link and 302s to it:

```
includes/class-wc-payments-redirect-service.php:84 redirect_to_capital_view_offer_page()
    Get_Account_Capital_Link::create()
        ->set_type( 'capital_financing_offer' )
        ->set_return_url( overview page )
        ->set_refresh_url( wcpay-loan-offer URL )
        ->send()
    → transact: POST /wcpay/accounts/capital_links
    → Stripe hosted URL
    → redirect_to( $capital_link['url'] )
```

Request class: `includes/core/server/request/class-get-account-capital-link.php` (API path `accounts/capital_links`, added in transact-platform-server PR #1382).

Valid `type` values are `capital_financing_offer` (accept/review) and `capital_financing_reporting` (view history).

## Two-Step Acceptance on Stripe's Side

On the Stripe hosted page there are **two distinct actions**:

1. **Accept** the offer
2. **Disburse funds**

Only disbursement flips `financing_summary.status` to `active` upstream, which is what the plugin checks. If a merchant only clicks "Accept" and leaves, `has_active_loan` stays `false` and the Capital Loans page will not appear.

## Capital Loans Admin Page Gate

The Capital Loans menu item and page are registered conditionally:

```
includes/admin/class-wc-payments-admin.php:457
    if ( $this->account->get_capital()['has_previous_loans'] ) {
        $this->admin_child_pages['wc-payments-capital'] = [
            'id'    => 'wc-payments-capital',
            'title' => __( 'Capital Loans', 'woocommerce-payments' ),
            'path'  => '/payments/loans',
            ...
        ];
    }
```

Because the menu is not registered when `has_previous_loans` is false, visiting `/payments/loans` directly returns WordPress's generic **"Sorry, you are not allowed to access this page"** error. This is not a capability failure — it is a missing route. Troubleshoot this by checking the cached account data, not user roles.

`$this->account->get_capital()` returns a shape like:

```php
[
    'has_active_loan'    => true|false,
    'has_previous_loans' => true|false,
    'loans'              => [ ... ],
]
```

## Account Data Cache Is Authoritative and Sticky

The capital flags come from the `wcpay_account_data` transient, populated from transact → Stripe. The cache does **not** refresh automatically after a Stripe-side state change (accept, disburse, repay). Until it is invalidated, the UI keeps showing the pre-change state.

TTL is set in `includes/class-database-cache.php:394-407`: **2 hours** for admin requests (or 2 minutes if the last fetch errored), **24 hours** for non-admin requests. So without manual invalidation, a Stripe state change can take up to 2 hours to surface in the admin UI.

Invalidate:

```bash
docker compose exec -u www-data wordpress wp --path=/var/www/html \
    transient delete wcpay_account_data
```

Inspect:

```bash
docker compose exec -T -u www-data wordpress wp --path=/var/www/html \
    eval 'print_r( \WC_Payments::get_account_service()->get_capital() );'
```

Once `has_previous_loans` is true, revisit any WooPayments admin page to repopulate the cache, and `/payments/loans` will resolve.

## Loan Data Surfaces

| Layer | Where |
|-------|-------|
| REST API base | `includes/admin/class-wc-rest-payments-capital-controller.php` (`rest_base = 'payments/capital'`) |
| Active loan endpoint | `GET /wc/v3/payments/capital/active_loan_summary` |
| Loan history endpoint | `GET /wc/v3/payments/capital/loans` |
| React data hooks | `client/data/capital/hooks.ts` (`useLoans`, `useActiveLoanSummary`) |
| React data resolvers | `client/data/capital/resolvers.ts` (`getLoans`, `getActiveLoanSummary` generators) |
| Active loan React component | `client/capital/` (gated on `has_active_loan` in `account['capital']`) |
| Loan approved inbox note | `includes/notes/class-wc-payments-notes-loan-approved.php` (triggered by `woocommerce_payments_account_refreshed`) |

## Testing Flow

To exercise the full flow in a local dev environment:

1. **Create an offer** in the Stripe Dashboard for your test connected account (`acct_…`). The offer starts in state `in_review`.
2. **Trigger the redirect** while logged into wp-admin:
   ```
   http://localhost:<PORT>/wp-admin/admin.php?page=wc-admin&wcpay-loan-offer
   ```
3. On the Stripe hosted page, **accept** the offer, then **disburse funds**. Only the second click makes the loan active upstream.
4. **Clear the cache** (see above). This is mandatory.
5. Reload wp-admin. The **Payments → Capital Loans** menu item now appears, and `/payments/loans` renders the active loan summary and history.

Alternative: call the transact endpoint directly to get the Stripe-hosted URL without the WP redirect step (endpoint added in transact-platform-server PR #1382). Only useful when debugging the link generation itself:

```bash
curl -X POST http://localhost:<TRANSACT_PORT>/wp-json/wpcom/v2/sites/<BLOG_ID>/wcpay/accounts/capital_links \
    -H "Content-Type: application/json" \
    -d '{"type":"capital_financing_offer","test_mode":true,"return_url":"...","refresh_url":"..."}'
```

Get the blog ID with `wp option get wpcom_blog_id` inside the WordPress container.

## Common Failure Modes

| Symptom | Cause | Fix |
|---------|-------|-----|
| "Sorry, you are not allowed to access this page" at `/payments/loans` | Capital menu not registered because `has_previous_loans` is false in cached account data | Clear `wcpay_account_data` transient and reload |
| `has_active_loan` stays false after clicking Accept | Stripe acceptance is two-step; funds not disbursed yet | Disburse funds on the Stripe hosted page |
| Redirect to `/payments/overview` with `wcpay-loan-offer-error=1` | `Get_Account_Capital_Link` request threw an exception (see `redirect_to_capital_view_offer_page()`) | Check transact logs for the `accounts/capital_links` call |
