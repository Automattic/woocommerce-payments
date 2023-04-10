# `Get_Account` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md).

## Description

The `WCPay\Core\Server\Request\Get_Account` class is used to construct the request for retrieving account data.
A note that this request only sends the test_mode flag only when the site is in the dev mode.

## Parameters

None.

## Filter

When using this request, provide the following filter:

- Name: `wcpay_get_account`.

## Example:

```php
$request = Get_Account::create();
$request->set_test_mode_only_when_dev_mode();
$request->send( 'wcpay_get_account' );
```
