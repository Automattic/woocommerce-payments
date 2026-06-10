# `Prepare_Terminal_Payment` request class

[This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Prepare_Terminal_Payment` class is used to construct the request for preparing a terminal PaymentIntent before confirmation.

## Parameters

When creating `Prepare_Terminal_Payment` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `pi_XXX` format.

An order ID must also be provided via `set_order_id()` so the server can validate the PaymentIntent metadata before preparing the payment.

## Filter

- Name: `wcpay_prepare_terminal_payment_request`
- Arguments: `WC_Order $order`

## Example:

```php
$request = Prepare_Terminal_Payment::create( $id );
$request->set_order_id( $order->get_id() );
$request->set_hook_args( $order );
$request->send();
```
