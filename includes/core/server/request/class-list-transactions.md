# `List_Transactions` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\List_Transactions` class is used to construct the request for listing transactions.

## Parameters


| Parameter                  | Setter                                                     | Immutable | Required | Default value |
|----------------------------|------------------------------------------------------------|:---------:|:--------:|:-------------:|
| `customer_currency_is`     | `set_customer_currency_is( string $customer_currency_is )` |     -     |    -     |       -       |
| `customer_currency_is_not` | `set_customer_currency_is_not( string $currency )`         |     -     |    -     |       -       |
| `deposit_id`               | `set_deposit_id( $deposit_id )`                            |     -     |    -     |       -       |
| `loan_id_is`               | `set_loan_id_is( string $loan_id )`                        |     -     |    -     |       -       |
| `match`                    | `set_match( string $match )`                               |     -     |    -     |       -       |
| `page`                     | `set_page( int $page )`                                    |    Yes    |    -     |       -       |
| `pagesize`                 | `set_page_size( int $page_size )`                          |    Yes    |    -     |     `25`      |
| `search`                   | `set_search( array $search )`                              |     -     |    -     |       -       |
| `sort`                     | `set_sort_by( string $sort )`                              |    Yes    |    -     |  `'created'`  |
| `direction`                | `set_sort_direction( string $direction )`                  |    Yes    |    -     |   `'desc'`    |
| `store_currency_is`        | `set_store_currency_is( string $currency )`                |     -     |    -     |       -       |
| `type_is`                  | `set_type_is( string $type_is )`                           |     -     |    -     |       -       |
| `type_is_not`              | `set_type_is_not( string $type_is_not )`                   |     -     |    -     |       -       |
| `source_device_is`         | `set_source_device_is( string $source_device_is )`         |     -     |    -     |       -       |
| `source_device_is_not`     | `set_source_device_is_not( string $source_device_is_not )` |     -     |    -     |       -       |


## Filter

- Name: `wcpay_list_transactions_request`
- Arguments: `$request`

## Example:

```php
$request = List_Transactions::create();
$request->set_customer_currency_is( $customer_currency_is );
$request->set_customer_currency_is_not( $currency );
$request->set_deposit_id( $deposit_id );
$request->set_loan_id_is( $loan_id );
$request->set_match( $match );
$request->set_page( $page );
$request->set_page_size( $page_size );
$request->set_search( $search );
$request->set_sort_by( $sort );
$request->set_sort_direction( $direction );
$request->set_store_currency_is( $currency );
$request->set_type_is( $type_is );
$request->set_type_is_not( $type_is_not );
$request->set_source_device_is( $source_device_is );
$request->set_source_device_is_not( $source_device_is_not );
$request->send();
```
