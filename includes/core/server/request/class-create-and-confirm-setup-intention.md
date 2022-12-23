# `Create_and_Confirm_Setup_Intention` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Create_and_Confirm_Setup_Intention` class is used to construct the request for creating and confirming a setup intention.

## Parameters


| Parameter        | Setter                                            | Immutable | Required | Default value |
|------------------|---------------------------------------------------|:---------:|:--------:|:-------------:|
| `customer`       | `set_customer( string $customer_id )`             |    Yes    |   Yes    |       -       |
| `description`    | `set_metadata( array $metadata )`                 |     -     |    -     |       -       |
| `payment_method` | `set_payment_method( string $payment_method_id )` |     -     |    -     |       -       |


## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_create_and_confirm_setup_intent_request`
- Arguments: 
   - `WCPay\Payment_Information $payment_information`
   - `bool $save_in_platform_account`
   - `bool $save_payment_method_to_platform`

## Example:

```php
$request = Create_and_Confirm_Setup_Intention::create();
$request->set_customer( $customer_id );
$request->set_metadata( $metadata );
$request->set_payment_method( $payment_method_id );
$request->send( 'wcpay_create_and_confirm_setup_intent_request', $payment_information, $save_in_platform_account, $save_payment_method_to_platform );
```
