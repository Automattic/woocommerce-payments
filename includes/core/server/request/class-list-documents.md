# `List_Documents` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\List_Documents` class is used to construct the request for listing documents.

## Parameters


| Parameter     | Setter                                    | Immutable | Required | Default value |
|---------------|-------------------------------------------|:---------:|:--------:|:-------------:|
| `match`       | `set_match( string $match )`              |     -     |    -     |       -       |
| `page`        | `set_page( int $page )`                   |    Yes    |    -     |       -       |
| `pagesize`    | `set_page_size( int $page_size )`         |    Yes    |    -     |     `25`      |
| `sort`        | `set_sort_by( string $sort )`             |    Yes    |    -     |  `'created'`  |
| `direction`   | `set_sort_direction( string $direction )` |    Yes    |    -     |   `'desc'`    |
| `type_is`     | `set_type_is( string $type_is )`          |     -     |    -     |       -       |
| `type_is_not` | `set_type_is_not( string $type_is_not )`  |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_list_documents_request`
- Arguments: `$request`

## Example:

```php
$request = List_Documents::create();
$request->set_match( $match );
$request->set_page( $page );
$request->set_page_size( $page_size );
$request->set_sort_by( $sort );
$request->set_sort_direction( $direction );
$request->set_type_is( $type_is );
$request->set_type_is_not( $type_is_not );
$request->send();
```
