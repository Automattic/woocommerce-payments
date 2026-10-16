# `Get_Reporting_Balance_Summary` request class

[ℹ️ This document is a part of **WooCommerce Payments Server Requests**](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_Reporting_Balance_Summary` class is used to construct the request for retrieving the Balance report summary. It targets the `/reporting/balance_summary` endpoint and returns the server response without client-side synthesis.

## Parameters

| Parameter    | Setter                                 | Immutable | Required | Default value |
| ------------ | -------------------------------------- | :-------: | :------: | :-----------: |
| `date_start` | `set_date_start( string $date_start )` |     -     |   Yes    |       -       |
| `date_end`   | `set_date_end( string $date_end )`     |     -     |   Yes    |       -       |
| `currency`   | `set_currency( string $currency )`     |     -     |   Yes    |       -       |

`date_start` and `date_end` must be valid REST API date-time values. `currency` must be an ISO-4217 currency code and is normalized to lowercase.

## Filter

-   Name: `wcpay_get_reporting_balance_summary_request`
-   Arguments: `$request`

## Example

```php
$request = Get_Reporting_Balance_Summary::create();
$request->set_date_start( '2024-03-01T00:00:00.000Z' );
$request->set_date_end( '2024-03-31T23:59:59.999Z' );
$request->set_currency( 'usd' );
$summary = $request->send();
```
