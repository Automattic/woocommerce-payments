# `Get_Account_Login_Data` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Get_Account_Login_Data` class is used to construct the request for getting one-time dashboard login url.

## Parameters

| Parameter         | Setter                                     | Immutable | Required | Default value |
|-------------------|--------------------------------------------|:---------:|:--------:|:-------------:|
| `redirect_url`    | `set_redirect_url( string $redirect_url )` |     -     |   Yes    |       -       |

## Filter

When using this request, provide the following filter and arguments:

- Name: `wpcay_get_account_login_data`
- Arguments: `string $redirect_url`

## Example:

```php
$request = Get_Account_Login_Data::create();
$request->send( 'wpcay_get_account_login_data', $redirect_url );
```
