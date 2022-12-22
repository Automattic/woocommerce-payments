`Cancel_Intention`

Used to sent cancel indentation request.

- List of immutable parameters: none
- List of required parameters: none
- List of default parameters: none

Name of the filter added: 'wcpay_cancel_intent_request'
Arguments passed to filter: WC_Order
Example:

```php
$request = Cancel_Intention::create( $order->get_transaction_id() );
$intent  = $request->send( 'wcpay_cancel_intent_request', $order );
```
