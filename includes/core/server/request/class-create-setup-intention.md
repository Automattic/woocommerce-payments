# `Create_Setup_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Create_Setup_Intention` class is used to construct the request for creating a setup intention.

## Parameters


| Parameter              | Setter                                               | Immutable | Required | Default value |
|------------------------|------------------------------------------------------|:---------:|:--------:|:-------------:|
| `customer`             | `set_customer( string $customer_id )`                |    Yes    |   Yes    |       -       |
| `payment_method_types` | `set_payment_method_types( array $payment_methods )` |     -     |   Yes    |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_create_setup_intent_request`
- Arguments: 

## Example:

```php
$request = Create_Setup_Intention::create();
$request->send( 'wcpay_create_setup_intent_request' );
```

!! NOT DONE!!! Remove this line once you have added an example and verified everything else
