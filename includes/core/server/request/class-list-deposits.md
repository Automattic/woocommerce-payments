# `List_Deposits` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

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
$request->set_match( $match );
$request->set_page( $page );
$request->set_page_size( $page_size );
$request->set_sort_by( $sort );
$request->set_sort_direction( $direction );
$request->set_status_is( $status_is );
$request->set_status_is_not( $status_is_not );
$request->set_store_currency_is( $store_currency_is );
$request->send();
```
