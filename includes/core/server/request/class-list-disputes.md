# `List_Disputes` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\List_Disputes` class is used to construct the request for listing disputes.

## Parameters


| Parameter         | Setter                                          | Immutable | Required | Default value |
|-------------------|-------------------------------------------------|:---------:|:--------:|:-------------:|
| `created_after`   | `set_created_after( string $created_after )`    |     -     |    -     |       -       |
| `created_before`  | `set_created_before( string $created_before )`  |     -     |    -     |       -       |
| `created_between` | `set_created_between( array $created_between )` |     -     |    -     |       -       |
| `currency_is`     | `set_currency_is( string $currency_is )`        |     -     |    -     |       -       |
| `match`           | `set_match( string $match )`                    |     -     |    -     |       -       |
| `page`            | `set_page( int $page )`                         |    Yes    |    -     |       -       |
| `pagesize`        | `set_page_size( int $page_size )`               |    Yes    |    -     |     `25`      |
| `search`          | `set_search( string $search )`                  |     -     |    -     |       -       |
| `sort`            | `set_sort_by( string $sort )`                   |    Yes    |    -     |  `'created'`  |
| `direction`       | `set_sort_direction( string $direction )`       |    Yes    |    -     |   `'desc'`    |
| `status_is`       | `set_status_is( string $status_is )`            |     -     |    -     |       -       |
| `status_is_not`   | `set_status_is_not( string $status_is_not )`    |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_list_disputes_request`
- Arguments: `$request`

## Example:

```php
$request = List_Disputes::create();
$request->send( 'wcpay_list_disputes_request', $request );
```

!! NOT DONE!!! Remove this line once you have added an example and verified everything else
