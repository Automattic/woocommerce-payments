# WooPayments Reports - Fees Contract

**Last updated:** 2026-05-20

## Scope

This document describes the current Phase 1 implementation contract for the WooPayments Reports Fees tab behind the `_wcpay_feature_reports_area` feature flag.

The implementation has two halves:

-   Backend REST endpoints that expose Fees rows and summaries by reusing the existing transactions APIs with Fees-specific filters.
-   A React/DataViews Fees table that owns URL-synced filters/search/sort/pagination and user-meta-synced view preferences.

## Endpoint

-   Controller: `WC_REST_Payments_Reports_Fees_Controller`.
-   Location: `includes/reports/class-wc-rest-payments-reports-fees-controller.php`.
-   Inheritance: extends `WC_REST_Payments_Reports_Transactions_Controller`.
-   REST base: `payments/reports/fees`.
-   Registration: include and instantiate the controller next to the existing reports controllers in `includes/class-wc-payments.php`.
-   Feature gate: `register_routes()` returns immediately when `! WC_Payments_Features::is_reports_area_enabled()`.
-   Test bootstrap: `tests/unit/bootstrap.php` requires the controller after `class-wc-rest-payments-reports-transactions-controller.php`.

When the flag is on, the controller registers:

-   `GET /payments/reports/fees`
-   `GET /payments/reports/fees/summary`

All routes use `check_permission`.

## Backend Filter Mapping

The child controller owns `get_fees_transaction_filters( WP_REST_Request $request ): array`. List and summary routes both use this helper so their filters stay aligned.

Report query params map to transaction API filters as follows:

| Report param          | Transaction filter  | Notes                                                         |
| --------------------- | ------------------- | ------------------------------------------------------------- |
| `payment_method_type` | `source_is`         | Exposed by the DataViews Method filter.                       |
| `type`                | `type_is_in`        | Exposed by the DataViews Type filter as one active value.      |
| `order_id`            | `order_id_is`       | Backend-supported; not currently exposed in the DataViews UI.  |
| `customer_email`      | `customer_email_is` | Backend-supported; not currently exposed in the DataViews UI.  |
| `deposit_id`          | `deposit_id`        | Backend-supported; not currently exposed in the DataViews UI.  |
| `date_before`         | `date_before`       | Formatted through `Request_Utils::format_transaction_date_by_timezone()`. |
| `date_after`          | `date_after`        | Formatted through `Request_Utils::format_transaction_date_by_timezone()`. |
| `date_between`        | `date_between`      | Each bound is formatted through `Request_Utils::format_transaction_date_by_timezone()`. |
| `match`               | `match`             | Backend-supported; not currently exposed in the DataViews UI.  |
| `search`              | `search`            | Exposed through DataViews global search.                      |
| `user_timezone`       | `user_timezone`     | Sent by the client for date conversion.                       |

The helper applies the default Fees ledger type filter only when the request does not include `type`.

Default `type_is_in`:

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

This keeps the Fees ledger focused on payment, refund, dispute, fee-refund, and platform-cost rows while omitting financing and card-reader hardware rows.

## Request Params

The list and summary routes accept the parent report params, with Fees-specific handling for `search` and `type` as array-capable filters:

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

The current UI exposes:

-   Date filter: `last_month`, `month_to_date`, `year_to_date`, and custom `on` / `before` / `after` / `between` values.
-   Method filter: one `payment_method_type`.
-   Type filter: one `type`.
-   Global search: serialized as a single-item `search` array.
-   Sort, page, and per-page controls through DataViews.

The PHP controller still honors `order_id`, `deposit_id`, `customer_email`, and `match`, but the current DataViews UI does not surface filter chips for them.

## Row Contract

The Fees list route uses the typed `List_Transactions` request, applies Fees filters, and prepares each returned transaction through the parent transactions controller response formatter. The Fees controller then removes the inherited `customer` object before returning the collection, and overrides `get_item_schema()` to remove the `customer` property so OPTIONS and other schema-discovery consumers don't advertise data the endpoint never returns.

Customer data (name, email, country) is intentionally not returned by this endpoint.

Fields returned to the client:

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

`payment_method.id` is not part of the current Fees row contract.

## DataViews Columns

The Fees table is rendered by `@wordpress/dataviews` in `client/reports/fees/index.tsx`.

Default visible fields:

-   `date`
-   `transaction_id`
-   `payment_method`
-   `type`
-   `order_id`
-   `transaction_currency`
-   `amount`
-   `fees`

Additional available fields:

-   `deposit_date`
-   `deposit_id`

Column behavior:

| UI column       | Row field              | Notes                                                       |
| --------------- | ---------------------- | ----------------------------------------------------------- |
| Date & time     | `date`                 | Default sort field, descending.                             |
| Transaction ID  | `transaction_id`       | Links to the transaction detail view.                       |
| Method          | `payment_method.type`  | Rendered through the payment-method label map.              |
| Type            | `type`                 | Rendered through the transaction-type label map.            |
| Order ID        | `order_id`             | Links to the WooCommerce order when present.                |
| Currency        | `transaction_currency` | Customer/presentment currency.                              |
| Gross amount    | `amount`               | Rendered in `deposit_currency`.                             |
| Fees total      | `fees`                 | Rendered in `deposit_currency`.                             |
| Settlement date | `deposit_date`         | Hidden by default.                                          |
| Payout ID       | `deposit_id`           | Hidden by default; links to the payout detail view when set. |

Product remains omitted. It is not present in the report-shaped row and is out of scope for the WooPayments-only MVP.

DataViews sort fields that differ from backend transaction fields are mapped in `client/reports/fees/use-fees-data.ts`:

| DataViews field          | Backend sort field |
| ------------------------ | ------------------ |
| `payment_method`         | `source`           |
| `transaction_currency`   | `customer_currency` |
| `deposit_date`           | `available_on`     |

Unmapped sort fields pass through unchanged.

## Client State

Fees view state is split between the URL and user meta.

URL-owned state:

-   `orderby`
-   `order`
-   `paged`
-   `per_page`
-   `search`
-   `date_preset`
-   `date_between`
-   `date_before`
-   `date_after`
-   `payment_method_type`
-   `type`

User-meta-owned state:

-   Key: `wc_payments_reports_fees_view`.
-   Fields: visible field ids, DataViews layout, and per-page preference.
-   Legacy migration: when the new key is empty, the client can derive initial visible fields from `wc_payments_reports_fees_hidden_columns`.

The DataViews search value is debounced before writing to the URL. Filter, sort, page, and per-page changes update the URL immediately.

## Date Filters

Fees date filters use shared date-filter primitives plus a Fees-specific DataViews adapter.

Shared primitives live in `client/reports/date-filter/`:

-   `DateFilterValue` / `DateOperator` types.
-   `PopoverBody`, calendar, inputs, operator select, presets, and formatters.
-   A standalone `DateFilter` component that owns its own chip and popover.

Fees-specific behavior lives in `client/reports/fees/date-filter-values.ts` and `client/reports/fees/custom-date-filter-popover.tsx`:

-   Preset DataViews values: `last_month`, `month_to_date`, `year_to_date`.
-   Custom DataViews values: `custom:on:<date>`, `custom:before:<date>`, `custom:after:<date>`, `custom:between:<start>:<end>`.
-   URL serialization clears stale date keys and writes only the active date shape.
-   Because DataViews owns the visible filter chip markup, Fees intercepts clicks and keyboard activation on the DataViews Date chip and anchors `CustomDateFilterPopover` to that chip.

The standalone `DateFilter` wrapper is not used by the Fees tab. It remains relevant as a reusable primitive for the planned non-DataViews Balance tab, where WooPayments will own the filter bar directly. Before using it for Balance, confirm whether the displayed range label should be `Between` or `Between (inc)`.

## Summary Contract

`GET /payments/reports/fees/summary` reuses the transactions summary response, with mapped Fees filters applied.

Expected fields:

-   `count`
-   `total`
-   `fees`
-   `net`
-   `currency`
-   `store_currencies`
-   `customer_currencies`
-   `sources`

The current client uses:

-   `count` for DataViews pagination totals.
-   `sources` to build the Method filter elements.

The Type filter does not depend on the summary response. It uses the local default fee-bearing type list so the filter remains available even when the current summary has no type facet data.

The Fees DataViews table currently does not render a gross/fees/net summary footer.

## Export Contract

Fees export is not implemented in this branch. There are no Fees-specific download routes, API client export wrappers, JavaScript CSV request helper, or Fees export action in the current UI.

Additional export work, including Fees-specific CSV columns such as business/store name and WooPayments account ID, belongs to a separate issue.

## Date Basis

Phase 1 uses the existing `date_*` filters:

-   `date` means the balance-transaction created timestamp.
-   `date_before`, `date_after`, and `date_between` filter on created date.
-   `deposit_date` remains an optional hidden column mapped from the transaction `available_on` value.

No settlement-date reporting mode is implemented in the current UI.

## Error, Empty, and Loading States

The Fees tab owns its own loading/error/empty behavior inside `client/reports/fees/index.tsx`.

-   Initial empty state: `No fees yet`.
-   Filtered empty state: `No fees to display`.
-   Error state: `Fees report unavailable`, with a `Reload report` action.
-   Loading-to-ready transitions announce the number of loaded fees through `@wordpress/a11y` `speak()`.

## Test Coverage

Current coverage includes:

-   PHP controller route registration, feature-gate behavior, filter mapping, row shaping, and summary forwarding.
-   API client Fees summary order-search mapping.
-   JavaScript data store actions, selectors, reducers, and resolvers for Fees rows and summaries.
-   Fees DataViews field rendering and query building.
-   Fees view URL/user-meta synchronization.
-   Date filter formatting, presets, URL serialization, and custom popover behavior.
