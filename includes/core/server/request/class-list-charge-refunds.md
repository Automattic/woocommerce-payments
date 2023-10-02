# `List_Charge_Refunds` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\List_Charge_Refunds` class is used to construct the request for listing refunds of a specific charge.

## Parameters


| Parameter | Setter                         | Immutable | Required | Default value |
|-----------|--------------------------------|:---------:|:--------:|:-------------:|
| `charge`  | `set_charge( string $charge_id )` |    Yes    |   Yes    |     None      |
| `limit`   | `set_limit( int $limit )`      |    No     |    No    |      100      |


## Filter

- Name: `wcpay_list_charge_refunds_request`
- Arguments: None

## Example:

```php
$request = List_Charge_Refunds::create();
$request->set_charge( 'ch_id' );
$request->set_limit( 100 ); // It is not required. You can also skip this setter.
$request->send();
```
