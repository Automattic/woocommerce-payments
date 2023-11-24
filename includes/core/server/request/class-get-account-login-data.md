# `Get_Account_Login_Data` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Get_Account_Login_Data` class is used to construct the request for getting one-time dashboard login url.
Note that this request sends the test_mode flag only when the site is in the dev mode.

## Parameters

| Parameter         | Setter                                     | Immutable | Required | Default value |
|-------------------|--------------------------------------------|:---------:|:--------:|:-------------:|
| `redirect_url`    | `set_redirect_url( string $redirect_url )` |     -     |   Yes    |       -       |

## Filter

- Name: `wpcay_get_account_login_data`
- Arguments: None.

## Example:

```php
$request = Get_Account_Login_Data::create();
$request->set_redirect_url( 'http://example.org/wp-admin/admin.php?page=wc-admin&path=/payments/overview' );
$request->send();
```
