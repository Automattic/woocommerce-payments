`Create_And_Confirm_Setup_Intention`

Used to construct create and confirm setup intention request.

- List of immutable parameters: `customer`, `confirm`
- List of required parameters: `customer`
- List of default parameters: `confirm` (as `true`), `metadata` (as empty array)

Name of the filter added: `wcpay_create_intention_request`
Arguments passed to filter: `Payment_Information`
Example:

```php
$request = Create_And_Confirm_Setup_Intention::create();
$request->set_customer( $customer_id );
$request->set_payment_method( $payment_information->get_payment_method() );
$request->set_metadata( $metadata );
$intent = $request->send( 'wcpay_create_and_confirm_setup_intention_request', $payment_information, false, $save_user_in_platform_checkout );
```
