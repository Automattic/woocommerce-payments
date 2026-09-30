# `Get_Transactions_Summary` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_Transactions_Summary` class is used to construct the request for retrieving aggregate totals (count, fees, net, etc.) for a transactions query. It targets the `/transactions/summary` endpoint and is used alongside `List_Transactions` so the table and its summary row share the same filter set.

## Parameters

| Parameter    | Setter                                     | Immutable | Required | Default value |
|--------------|--------------------------------------------|:---------:|:--------:|:-------------:|
| `deposit_id` | `set_deposit_id( ?string $deposit_id )`    |     -     |    -     |       -       |
| `search`     | `set_search( array $search )`              |     -     |    -     |       -       |
| _arbitrary_  | `set_filters( array $filters )`            |     -     |    -     |       -       |

`set_filters()` accepts the same filter shape as `List_Transactions` (`source_is`, `type_is_in`, `date_between`, `match`, etc.) and forwards each entry to a typed setter when one exists, or to a raw query param otherwise. Empty values (`null`, `''`, `[]`) are skipped so callers can pass the unfiltered request payload directly.

## Filter

- Name: `wcpay_get_transactions_summary_request`
- Arguments: `$request`

## Example

```php
$request = Get_Transactions_Summary::create();
$request->set_filters(
    [
        'source_is'  => 'card',
        'type_is_in' => [ 'charge', 'refund' ],
        'search'     => [ 'txn_123' ],
    ]
);
$request->set_deposit_id( 'po_mock' );
$summary = $request->send();
```
