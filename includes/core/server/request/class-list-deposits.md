# `List_Deposits` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\List_Deposits` class is used to construct the request for listing deposits.

## Parameters


| Parameter           | Setter                                               | Immutable | Required | Default value |
|---------------------|------------------------------------------------------|:---------:|:--------:|:-------------:|
| `match`             | `set_match( string $match )`                         |     -     |    -     |       -       |
| `page`              | `set_page( int $page )`                              |    Yes    |    -     |       -       |
| `pagesize`          | `set_page_size( int $page_size )`                    |    Yes    |    -     |     `25`      |
| `sort`              | `set_sort_by( string $sort )`                        |    Yes    |    -     |  `'created'`  |
| `direction`         | `set_sort_direction( string $direction )`            |    Yes    |    -     |   `'desc'`    |
| `status_is`         | `set_status_is( string $status_is )`                 |     -     |    -     |       -       |
| `status_is_not`     | `set_status_is_not( string $status_is_not )`         |     -     |    -     |       -       |
| `store_currency_is` | `set_store_currency_is( string $store_currency_is )` |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_list_deposits_request`
- Arguments: `$request`

## Example:

```php
$request = List_Deposits::create();
$request->send( 'wcpay_list_deposits_request', $request );
```

!! NOT DONE!!! Remove this line once you have added an example and verified everything else
