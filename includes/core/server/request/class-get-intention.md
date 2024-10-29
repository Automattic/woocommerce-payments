# `Get_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_Intention` class is used to construct the request for retrieving an intention.

## Parameters

When creating `Get_Intention` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `pi_XXX` format.
There are no additional parameters for this request.

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_get_intent_request`
- Arguments: `WC_Order $order`

## Example:

```php
$request = Get_Intention::create( $id );
$request->set_hook_args( $order )
$request->send();
```
