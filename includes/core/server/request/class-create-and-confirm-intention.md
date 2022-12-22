`Create_And_Confirm_Intention`

Used to construct create and confirm intention request.

- List of immutable parameters: `amount`, `currency`, `payment_method`
- List of required parameters: `amount`, `currency`, `payment_method`, `customer`, `metadata`
- List of default parameters: `confirm` (as `true`), `capture_method` (as ‘automatic’)

Name of the filter added: `wcpay_create_intention_request`
Arguments passed to filter: `Payment_Information`

Example:

```php
$request = Create_And_Confirm_Intention::create();
$request->set_amount( $converted_amount );
$request->set_currency_code( $currency );
$request->set_payment_method( $payment_information->get_payment_method() );
$request->set_customer( $customer_id );
$request->set_capture_method( $payment_information->is_using_manual_capture() );
$request->set_metadata( $metadata );
$request->set_level3( $this->get_level3_data_from_order( $order ) );
$request->set_off_session( $payment_information->is_merchant_initiated() );
$request->set_payment_methods( $payment_methods );
$request->set_cvc_confirmation( $payment_information->get_cvc_confirmation() );
// Make sure that setting fingerprint is performed after setting metadata becaouse metadata will override any values you set before for metadata param.
$request->set_fingerprint( $payment_information->get_fingerprint() );
if ( $save_payment_method_to_store ) {
 $request->setup_future_usage();
}
if ( $scheduled_subscription_payment ) {
 $mandate = $this->get_mandate_param_for_renewal_order( $order );
 if ( $mandate ) {
   $request->set_mandate( $mandate );
 }
}
$intent = $request->send( 'wcpay_create_intention_request', $payment_information );
```
