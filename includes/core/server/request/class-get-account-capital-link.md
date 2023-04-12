# `Get_Account_Capital_Link` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Get_Account_Capital_Link` class is used to construct the request for retrieving an intention.

## Parameters

| Parameter         | Setter                                     | Immutable | Required | Default value |
|-------------------|--------------------------------------------|:---------:|:--------:|:-------------:|
| `type`            | `set_type( string $type )`                 |     -     |   Yes    |       -       |
| `redirect_url`    | `set_redirect_url( string $redirect_url )` |     -     |   Yes    |       -       |
| `refresh_url`     | `set_refresh_url( string $refresh_url )`   |     -     |   Yes    |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_get_account_capital_link`
- Arguments: None.

## Example:

```php
$request = Get_Account_Capital_Link::create();
$request->send( 'wcpay_get_account_capital_link' );
```
