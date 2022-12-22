
`Capure_Intention`

Used to sent capture indentation.

- List of immutable parameters: `amount_to_capture`
- List of required parameters: `amount_to_capture`
- List of default parameters: `level3`( as empty array)

Name of the filter added: ‘wcpay_capture_intent_request’
Arguments passed to filter: `WC_Order`
Example:

```php
$capture_intention_request = Capture_Intention::create( $intent_id );
$capture_intention_request->set_amount_to_capture( WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
if ( $include_level3 ) {
 $capture_intention_request->set_level3( $this->get_level3_data_from_order( $order ) );
}
```



