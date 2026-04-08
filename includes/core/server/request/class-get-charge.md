# `Get_Charge` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_Charge` class is used to construct the request for retrieving a charge.

## Parameters

When creating `Get_Charge` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `ch_XXX` format.
There are no additional parameters for this request.

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_get_charge_request`
- Arguments: None or charge id.

## Example:

```php
$request = Get_Charge::create( $id );
$request->send();
```
