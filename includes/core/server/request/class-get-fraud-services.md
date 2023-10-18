# `Get_Fraud_Services` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md).

## Description

The `WCPay\Core\Server\Request\Get_Fraud_Services` class is used to construct the request for retrieving 
public fraud services config.
Note that this request sends the test_mode flag only when the site is in the dev mode.

## Parameters

None.

## Filter

When using this request, provide the following filter:

- Name: `wcpay_get_fraud_services`.

## Example:

```php
$request = Get_Fraud_Services::create();
$request->send();
```
