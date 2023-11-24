# `Capture_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Capture_Intention` class is used to construct the request for capturing an intention.

## Parameters

When creating `Capture_Intention` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `pi_XXX` format.
| Parameter           | Setter                                 | Immutable | Required | Default value |
|---------------------|----------------------------------------|:---------:|:--------:|:-------------:|
| `amount_to_capture` | `set_amount_to_capture( int $amount )` |    Yes    |   Yes    |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_capture_intent_request`
- Arguments: `WC_Order $order`

## Example:

```php
$request = Capture_Intention::create( $id );
$request->set_amount_to_capture( $amount );
$request->set_hook_args( $order );
$request->send();
```
