# `Get_Reporting_Payment_Activity` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_Reporting_Payment_Activity` class is used to construct the request for retrieving payment activity.

## Parameters

| Parameter   | Setter                                    | Immutable | Required | Default value |
|-------------|-------------------------------------------|:---------:|:--------:|:-------------:|
| `date_start`| `set_date_start( string $date_start )`    |    No     |    Yes   |       -       |
| `date_end`  | `set_date_end( string $date_end )`        |    No     |    Yes   |       -       |
| `timezone`  | `set_timezone( string $timezone )`        |    No     |    Yes   |       -       |
| `currency`  | `set_currency( string $currency )`        |    No     |    Yes   |       -       |

The `date_start` and `date_end` parameters should be in the 'YYYY-MM-DDT00:00:00' format.
The `timezone` parameter can be passed as an offset or as a [timezone name](https://www.php.net/manual/en/timezones.php).
The `currency` parameter should be a lower-cased ISO currency code of a store supported currency.

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_get_payment_activity`

## Example:

```php
$request = Get_Reporting_Payment_Activity::create();
$request->set_date_start( $date_start );
$request->set_date_end( $date_end );
$request->set_timezone( $timezone );
$request->set_currency( $currency );
$request->send();
```

## Exceptions

- `Invalid_Request_Parameter_Exception` - Thrown when the provided date or timezone is not in expected format.
