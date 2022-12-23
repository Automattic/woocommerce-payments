# `Update_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Update_Intention` class is used to construct the request for updating an intention.

## Parameters

When creating `Update_Intention` requests, the item ID must be provided to the `::create()` method. The identifier should be in the `pi_XXX` format.
| Parameter                | Setter                                                        | Immutable | Required | Default value |
|--------------------------|---------------------------------------------------------------|:---------:|:--------:|:-------------:|
| `amount`                 | `set_amount( int $amount )`                                   |    Yes    |    -     |       -       |
| `currency`               | `set_currency_code( string $currency_code )`                  |     -     |    -     |       -       |
| `customer`               | `set_customer( string $customer_id )`                         |     -     |    -     |       -       |
| `metadata`               | `set_fingerprint( $fingerprint = '' )`                        |     -     |    -     |       -       |
| `description`            | `set_metadata( $metadata )`                                   |     -     |    -     |       -       |
| `payment_country`        | `set_payment_country( string $payment_country )`              |     -     |    -     |       -       |
| `payment_method_options` | `set_payment_method_options( array $payment_method_options )` |     -     |    -     |       -       |
| `payment_method_types`   | `set_payment_method_types( array $payment_methods )`          |     -     |    -     |       -       |
| `setup_future_usage`     | `setup_future_usage()`                                        |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_update_intention_request`
- Arguments: `WC_Order $order`

## Example:

```php
$request = Update_Intention::create( $id );
$request->send( 'wcpay_update_intention_request', $order );
```

!! NOT DONE!!! Remove this line once you have added an example and verified everything else
