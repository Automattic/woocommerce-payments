# `List_Fraud_Outcome_Transactions` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions` class is used to construct the request for listing fraud outcome transactions.

## Parameters


| Parameter                  | Setter                                                     | Immutable | Required | Default value |
|----------------------------|------------------------------------------------------------|:---------:|:--------:|:-------------:|
| `status`                   | `set_status( string $status )`                             |    Yes    |    -     |       -       |
| `page`                     | `set_page( int $page )`                                    |    Yes    |    -     |       -       |
| `pagesize`                 | `set_page_size( int $page_size )`                          |    Yes    |    -     |     `25`      |
| `search`                   | `set_search( array $search )`                              |     -     |    -     |       -       |
| `search_term`              | `set_search_term( string $search_term )`                   |    Yes    |    -     |   `'desc'`    |
| `sort`                     | `set_sort_by( string $sort )`                              |    Yes    |    -     |  `'created'`  |
| `direction`                | `set_sort_direction( string $direction )`                  |    Yes    |    -     |   `'desc'`    |



## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_list_fraud_outcome_transactions_request`
- Arguments: `$request`

## Example:

```php
$request = List_Fraud_Outcome_Transactions::create();
$request->set_status( $status );
$request->set_page( $page );
$request->set_page_size( $page_size );
$request->set_search( $search );
$request->set_search_term( $search_term );
$request->set_sort_by( $sort );
$request->set_sort_direction( $direction );
$request->send( 'wcpay_list_transactions_request', $request );
```
