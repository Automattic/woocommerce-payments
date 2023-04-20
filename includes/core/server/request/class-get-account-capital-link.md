# `Get_Account_Capital_Link` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Get_Account_Capital_Link` class is used to construct the request for retrieving a one-time capital link. 
Expected response is an account link object with create, expires_at, and url fields.

## Parameters

| Parameter         | Setter                                   | Immutable | Required | Default value |
|-------------------|------------------------------------------|:---------:|:--------:|:-------------:|
| `type`            | `set_type( string $type )`               |     -     |   Yes    |       -       |
| `return_url`      | `set_return_url( string $return_url )`   |     -     |   Yes    |       -       |
| `refresh_url`     | `set_refresh_url( string $refresh_url )` |     -     |   Yes    |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_get_account_capital_link`
- Arguments: None.

## Example:

```php
$request = Get_Account_Capital_Link::create();
$request->set_type( 'capital_financing_offer' );
$request->set_return_url( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=/payments/overview' );
$request->set_refresh_url( 'http://example.org/wp-admin/admin.php?wcpay-loan-offer' );
$request->send( 'wcpay_get_account_capital_link' );
```
