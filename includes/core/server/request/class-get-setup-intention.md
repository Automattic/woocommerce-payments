# `Get_Setup_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Get_Setup_Intention` class is used to construct the request for retrieving a setup intention.

## Parameters

When creating `Get_Setup_Intention` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `seti_XXX` format.
There are no additional parameters for this request.

## Filter

- Name: `wcpay_get_setup_intent_request`

## Example:

```php
$request = Get_Setup_Intention::create( $id );
$request->send();
```
