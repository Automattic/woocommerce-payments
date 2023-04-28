# `Refund_Charge` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Refund_Charge` class is used to construct the request for refunding a charge.

## Parameters


| Parameter | Setter                            | Immutable | Required | Default value |
|-----------|-----------------------------------|:---------:|:--------:|:-------------:|
| `charge`  | `set_charge( string $charge_id )` |    Yes    |   Yes    |     None      |
| `amount`  | `set_amount( int $amount )`       |    No     |    No    |     null      |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_refund_charge_request`
- Arguments: None

## Example:

```php
$request = Refund_Charge::create( );
$request->set_charge( 'ch_xxxxx' );
$request->set_amount( 100 ); // It is not required. You can also skip this setter.
$request->send( 'wcpay_refund_charge_request' );
```
