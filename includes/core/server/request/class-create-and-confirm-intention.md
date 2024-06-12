# `Create_and_Confirm_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Create_and_Confirm_Intention` class is used to construct the request for creating and confirming an intention.

## Parameters


| Parameter               | Setter                                               | Immutable | Required | Default value |
|-------------------------|------------------------------------------------------|:---------:|:--------:|:-------------:|
| `amount`                | `set_amount( int $amount )`                          |    Yes    |   Yes    |       -       |
| `capture_method`        | `set_capture_method( bool $manual_capture = false )` |     -     |    -     | `'automatic'` |
| `currency`              | `set_currency_code( string $currency_code )`         |    Yes    |   Yes    |       -       |
| `customer`              | `set_customer( string $customer_id )`                |     -     |   Yes    |       -       |
| `cvc_confirmation`      | `set_cvc_confirmation( $cvc_confirmation = null )`   |     -     |    -     |       -       |
| `metadata`              | `set_fingerprint( string $fingerprint = '' )`        |     -     |   Yes    |       -       |
| `mandate`               | `set_mandate( string $mandate )`                     |     -     |    -     |       -       |
| `description`           | `set_metadata( array $metadata )`                    |     -     |    -     |       -       |
| `off_session`           | `set_off_session( bool $off_session = true )`        |     -     |    -     |       -       |
| `payment_method`        | `set_payment_method( string $payment_method_id )`    |    Yes    |   Yes    |       -       |
| `payment_method_types`  | `set_payment_method_types( array $payment_methods )` |     -     |    -     |       -       |
| `payment_methods_types` | `set_payment_methods( array $payment_methods )`      |     -     |    -     |       -       |
| `setup_future_usage`    | `setup_future_usage()`                               |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_create_and_confirm_intent_request`
- Arguments: `WCPay\Payment_Information $payment_information`

## Example:

```php
$request = Create_and_Confirm_Intention::create();
$request->set_amount( $amount );
$request->set_capture_method( $manual_capture );
$request->set_currency_code( $currency_code );
$request->set_customer( $customer_id );
$request->set_cvc_confirmation( $cvc_confirmation );
$request->set_fingerprint( $fingerprint );
$request->set_mandate( $mandate );
$request->set_metadata( $metadata );
$request->set_off_session( $off_session );
$request->set_payment_method( $payment_method_id );
$request->set_payment_method_types( $payment_methods );
$request->set_payment_methods( $payment_methods );
$request->setup_future_usage();
$request->set_hook_args( $payment_information );
$request->send();
```
