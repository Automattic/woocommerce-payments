# WooPayments Reports - Fees Contract

**Last updated:** 2026-05-20

## Scope

This document is the implementation contract for Phase 1 of the RSM reconciliation reports work: the Fees report tab behind the `_wcpay_feature_reports_area` feature flag.

It pins the REST route, filters, row shape, summary shape, export expectations, fee-bearing default, and date-basis semantics before any PHP, data-store, or UI code is added.

## Endpoint

-   Controller: `WC_REST_Payments_Reports_Fees_Controller`.
-   Location: `includes/reports/class-wc-rest-payments-reports-fees-controller.php`.
-   Inheritance: extends `WC_REST_Payments_Reports_Transactions_Controller`.
-   REST base: `payments/reports/fees`.
-   Registration: include and instantiate the controller unconditionally next to the existing reports controllers in `includes/class-wc-payments.php`.
-   Feature gate: the controller's `register_routes()` returns immediately when `! WC_Payments_Features::is_reports_area_enabled()`.
-   Test bootstrap: `tests/unit/bootstrap.php` must require the new controller after `class-wc-rest-payments-reports-transactions-controller.php`.

When the flag is on, the controller registers:

-   `GET /payments/reports/fees`
-   `GET /payments/reports/fees/(?P<id>\w+)`
-   `GET /payments/reports/fees/summary`
-   `POST /payments/reports/fees/download`
-   `GET /payments/reports/fees/download/(?P<export_id>[^/\\\\%]+)`

All routes use `check_permission`.

## Filter Mapping

The child controller owns a single helper, `get_fees_transaction_filters( WP_REST_Request $request ): array`. List, summary, and download routes all use this helper so they cannot drift.

Report query params map to transaction API filters as follows:

| Report param           | Transaction filter     |
| ---------------------- | ---------------------- |
| `payment_method_type`  | `source_is`            |
| `type`                 | `type_is_in`           |
| `order_id`             | `order_id_is`          |
| `customer_email`       | `customer_email_is`    |
| `deposit_id`           | `deposit_id`           |
| `date_before`          | `date_before`          |
| `date_after`           | `date_after`           |
| `date_between`         | `date_between`         |
| `match`                | `match`                |
| `search`               | `search`               |
| `user_timezone`        | `user_timezone`        |

The helper applies the default Fees ledger type filter only when the request does not include `type`.

Default `type_is_in` for Phase 1:

-   `charge`
-   `payment`
-   `payment_failure_refund`
-   `payment_refund`
-   `refund`
-   `refund_failure`
-   `dispute`
-   `dispute_reversal`
-   `fee_refund`
-   `network_costs`

This is intentionally the current transaction type label set minus financing and card-reader hardware rows. It keeps the Fees ledger focused on payment, refund, dispute, fee-refund, and platform-cost rows while preserving the documented MVP exception that reader hardware fees are ignored. If the server later exposes a more precise instant-payout fee type, add it to this allowlist in the same change that adds the display label.

## Request Params

The collection route inherits the parent report params:

-   `date_before`
-   `date_after`
-   `date_between`
-   `order_id`
-   `deposit_id`
-   `customer_email`
-   `search`
-   `payment_method_type`
-   `type`
-   `match`
-   `user_timezone`
-   `page`
-   `per_page`
-   `sort`
-   `direction`

For Phase 1, the UI exposes `type` as a single-select filter, matching the Transactions report's Type filter. The REST controller still normalizes either a single value or an array to `type_is_in`, so older URLs and direct REST consumers remain tolerant.

## Row Contract

The Fees list starts from `WC_REST_Payments_Reports_Transactions_Controller::prepare_item_for_response()`, strips customer PII that the Fees UI does not render, and returns report-shaped rows rather than raw transaction-cache rows.

Fields:

-   `transaction_id`
-   `date`
-   `payment_id`
-   `channel`
-   `payment_method: { type }`
-   `type`
-   `transaction_currency`
-   `amount`
-   `exchange_rate`
-   `deposit_currency`
-   `fees`
-   `net_amount`
-   `order_id`
-   `risk_level`
-   `deposit_date`
-   `deposit_id`
-   `deposit_status`

`payment_method.id` is not part of the Phase 1 contract. The inherited schema mentions it, but the parent response does not populate it and the MVP columns do not need it.

Column mapping:

| UI column       | Row field              | Notes                                              |
| --------------- | ---------------------- | -------------------------------------------------- |
| Date & time     | `date`                 | Default sort field, descending.                    |
| Method          | `payment_method.type`  | Rendered through the payment-method label map.     |
| Type            | `type`                 | Rendered through the transaction-type label map.   |
| Order ID        | `order_id`             | Links to the WooCommerce order when possible.      |
| Transaction ID  | `transaction_id`       | Links to the transaction detail view.              |
| Currency        | `transaction_currency` | Customer/presentment currency.                     |
| Gross amount    | `amount`               | Store/settlement currency.                         |
| Fees total      | `fees`                 | Store/settlement currency.                         |
| Settlement date | `deposit_date`         | Hidden by default.                                 |
| Payout ID       | `deposit_id`           | Hidden by default; render `-` or `N/A` when empty. |

Product is omitted. It is not present in the report-shaped row and is out of scope for the WooPayments-only MVP.

Currency semantics are settled for implementation: `amount` and `fees` render in `deposit_currency`; `transaction_currency` is shown as a separate reference column.

## Summary Contract

`GET /payments/reports/fees/summary` reuses the transactions summary response, with the mapped Fees filters applied.

Expected fields:

-   `count`
-   `total`
-   `fees`
-   `net`
-   `currency`
-   `store_currencies`
-   `customer_currencies`
-   `sources`

The Fees footer shows:

-   count: `count`
-   gross total: `total`
-   fees total: `fees`

The Method filter uses `sources` from this response. The Type filter uses the current report type label set as the MVP fallback and allows one active type value at a time.

## Export Contract

The Fees export endpoint reuses the transactions CSV export path with the mapped Fees filters applied.

The export is machine-readable:

-   no decorative header rows
-   current report period and filters included in the request
-   same export polling flow as existing WooPayments report exports

CSV columns are the existing transactions export columns plus:

-   Business/store name
-   WooPayments account ID

These two columns should be added server-side when possible so the emailed file and downloaded file match. If the upstream transactions export cannot add them in this phase, the route still uses the same public Fees export contract and the gap should be tracked explicitly.

## Date Basis

Phase 1 ships on the existing `date_*` filters. In this version:

-   `date` means the balance-transaction created timestamp.
-   `date_before`, `date_after`, and `date_between` filter on created date.
-   The UI includes the date-basis footnote: "Dates reflect when each event was created - settlement-date reporting is coming."

## Implementation Notes

-   The list route uses the typed `List_Transactions` request and wires only the Fees-owned paging, sorting, and filter surface.
-   Summary and export route callbacks use `forward_request()` to call Fees-specific API-client wrappers that delegate to the transactions summary/download endpoints. These wrappers mirror the existing Transactions order-search mapping before forwarding filters.
-   Tests assert converted transaction filter keys (`source_is`, `type_is_in`, `order_id_is`, `customer_email_is`) rather than raw report params.
-   The feature flag is tested by direct controller instantiation: flag on registers the Fees routes; flag off registers none.
