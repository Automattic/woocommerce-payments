# `Cancel_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Cancel_Intention` class is used to construct the request for canceling an intention.

## Parameters

When creating `Cancel_Intention` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `pi_XXX` format.
There are no additional parameters for this request.

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_cancel_intent_request`
- Arguments: `WC_Order $order`

## Example:

```php
$request = Cancel_Intention::create( $id );
$request->send( 'wcpay_cancel_intent_request', $order );
```
