# `WooPay_Create_and_Confirm_Setup_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\WooPay_Create_and_Confirm_Setup_Intention` class is used to construct the request for creating and confirming a setup intention for WooPay.

## Parameters


| Parameter                         | Setter                                                | Immutable | Required | Default value |
|-----------------------------------|-------------------------------------------------------|:---------:|:--------:|:-------------:|
| `customer`                        | `set_customer( string $customer_id )`                 |    Yes    |   Yes    |       -       |
| `is_platform_payment_method`      | `set_is_platform_payment_method( $is = true )`        |     -     |    -     |       -       |
| `description`                     | `set_metadata( array $metadata )`                     |     -     |    -     |       -       |
| `payment_method`                  | `set_payment_method( string $payment_method_id )`     |     -     |    -     |       -       |
| `save_in_platform_account`        | `set_save_in_platform_account( $save = true )`        |     -     |    -     |       -       |
| `save_payment_method_to_platform` | `set_save_payment_method_to_platform( $save = true )` |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_create_and_confirm_setup_intent_request`
- Arguments: 
   - `WCPay\Payment_Information $payment_information`
   - `bool $save_in_platform_account`
   - `bool $save_payment_method_to_platform`

## Example:

```php
$request = WooPay_Create_and_Confirm_Setup_Intention::create();
$request->set_customer( $customer_id );
$request->set_is_platform_payment_method( $is );
$request->set_metadata( $metadata );
$request->set_payment_method( $payment_method_id );
$request->set_save_in_platform_account( $save );
$request->set_save_payment_method_to_platform( $save );
$request->send( 'wcpay_create_and_confirm_setup_intent_request', $payment_information, $save_in_platform_account, $save_payment_method_to_platform );
```
