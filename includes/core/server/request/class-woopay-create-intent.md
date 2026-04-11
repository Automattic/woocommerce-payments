# `WooPay_Create_Intent` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\WooPay_Create_Intent` class is used to construct the request for creating an intention for WooPay.

## Parameters


| Parameter                         | Setter                                                | Immutable | Required | Default value |
|-----------------------------------|-------------------------------------------------------|:---------:|:--------:|:-------------:|
| `amount`                          | `set_amount( int $amount )`                           |    Yes    |   Yes    |       -       |
| `capture_method`                  | `set_capture_method( bool $manual_capture = false )`  |     -     |    -     |       -       |
| `currency`                        | `set_currency_code( string $currency_code )`          |     -     |   Yes    |       -       |
| `customer`                        | `set_customer( string $customer_id )`                 |     -     |    -     |       -       |
| `metadata`                        | `set_fingerprint( string $fingerprint = '' )`         |     -     |    -     |       -       |
| `mandate`                         | `set_mandate( string $mandate )`                      |     -     |    -     |       -       |
| `description`                     | `set_metadata( array $metadata )`                     |     -     |    -     |       -       |
| `payment_method`                  | `set_payment_method( string $payment_method_id )`     |     -     |    -     |       -       |
| `payment_method_types`            | `set_payment_method_types( array $payment_methods )`  |     -     |    -     |       -       |
| `save_payment_method_to_platform` | `set_save_payment_method_to_platform( bool $toggle )` |    Yes    |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_create_intent_request`
- Arguments: `WC_Order $order`

## Example:

```php
$request = WooPay_Create_Intent::create();
$request->set_amount( $amount );
$request->set_capture_method( $manual_capture );
$request->set_currency_code( $currency_code );
$request->set_customer( $customer_id );
$request->set_fingerprint( $fingerprint );
$request->set_mandate( $mandate );
$request->set_metadata( $metadata );
$request->set_payment_method( $payment_method_id );
$request->set_payment_method_types( $payment_methods );
$request->set_save_payment_method_to_platform( $toggle );
$request->set_hook_args( $order );
$request->send();
```
