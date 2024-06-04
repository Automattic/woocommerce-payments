# `List_Authorizations` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\List_Authorizations` class is used to construct the request for listing authorizations.

## Parameters


| Parameter   | Setter                                    | Immutable | Required | Default value |
|-------------|-------------------------------------------|:---------:|:--------:|:-------------:|
| `page`      | `set_page( int $page )`                   |    Yes    |    -     |       -       |
| `pagesize`  | `set_page_size( int $page_size )`         |    Yes    |    -     |     `25`      |
| `sort`      | `set_sort_by( string $sort )`             |    Yes    |    -     |  `'created'`  |
| `direction` | `set_sort_direction( string $direction )` |    Yes    |    -     |   `'desc'`    |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_list_authorizations_request`
- Arguments: `$request`

## Example:

```php
$request = List_Authorizations::create();
$request->set_page( $page );
$request->set_page_size( $page_size );
$request->set_sort_by( $sort );
$request->set_sort_direction( $direction );
$request->send();
```
