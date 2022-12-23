# `Create_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Create_Intention` class is used to construct the request for creating an intention.

## Parameters


| Parameter              | Setter                                               | Immutable | Required | Default value |
|------------------------|------------------------------------------------------|:---------:|:--------:|:-------------:|
| `amount`               | `set_amount( int $amount )`                          |    Yes    |   Yes    |       -       |
| `capture_method`       | `set_capture_method( bool $manual_capture = false )` |     -     |    -     |       -       |
| `currency`             | `set_currency_code( string $currency_code )`         |     -     |   Yes    |       -       |
| `customer`             | `set_customer( string $customer_id )`                |     -     |    -     |       -       |
| `metadata`             | `set_fingerprint( string $fingerprint = '' )`        |     -     |    -     |       -       |
| `mandate`              | `set_mandate( string $mandate )`                     |     -     |    -     |       -       |
| `description`          | `set_metadata( array $metadata )`                    |     -     |    -     |       -       |
| `payment_method`       | `set_payment_method( string $payment_method_id )`    |     -     |    -     |       -       |
| `payment_method_types` | `set_payment_method_types( array $payment_methods )` |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_create_intent_request`
- Arguments: `WC_Order $order`

## Example:

```php
$request = Create_Intention::create();
$request->send( 'wcpay_create_intent_request', $order );
```

!! NOT DONE!!! Remove this line once you have added an example and verified everything else
