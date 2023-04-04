# `List_Charge_Refunds` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\List_Charge_Refunds` class is used to construct the request for refunding a refunds for specific charge.

## Parameters

When creating `List_Charge_Refunds` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `ch_XXX` format.

| Parameter | Setter                    | Immutable | Required | Default value |
|-----------|---------------------------|:---------:|:--------:|:-------------:|
| `limit`   | `set_limit( int $limit )` |    No     |    No    |      100      |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_list_charge_refunds_request`
- Arguments: None

## Example:

```php
$request = List_Charge_Refunds::create( $id );
$request->set_limit( 100 ); // It is not required. You can also skip this setter.
$request->send( 'wcpay_refund_charge_request' );
```
