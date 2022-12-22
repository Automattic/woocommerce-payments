# WooCommerce Payments Server Requests

This document will outline the concepts and documentation of WooCommerce Payments Request classes. The purpose of request classes is to provide an easy way to send API requests towards WooCommerce Payments Server, where type safety, validation and builder pattern is used to built requests towards WooCommerce Payments Server. To make it easier for other parties to use it and extend it, every request class has its own filter that can be hooked on, and you could change the behavior or even set your own parameters while maintain all functionality from the original request class

## Creating request class

A new abstract request class have been added that outlines functionalities that are standard for each request class. New defined parameters constants (`IMMUTABLE_PARAMS`, `REQUIRED_PARAMS`, `DEFAULT_PARAMS`) holds some basic functionality for request parameters. Immutable parameters constant is sued to maintain immutability when request is extended or altered via filters. One good example is “amount” parameter in any intent API request calls, where “amount” cannot be changed in any filters. Default parameters constant is used to hold default value for request parameters. Using this approach will make building a request easier by not calling setters for default parameters. Required parameters constant holds a list of parameters that are required. This constant is used during execution of the request, where each parameter is checked and validated. 
To set request parameters, each request class has its own setter functions. Some of these functions have their own validators. Using validators will force us to pass variables that are in correct type and in correct format.
Some request classes uses ID to send request. For example, update intent requests needs to know the ID of intent it needs to update. You can pass ID to request class and request class will know how to handle it.
To create a request, use a static function called “create” and pass ID if needed. All dependencies are already taken care of. More examples will be provided in this document, where every request will be displayed with built steps.

## Request class responses

The response of API requests is handled within request class. The default response is class that implemented ArrayAccess interface that mimic array like behavior to classes. Some response classes have its own custom defined response (Intention request classes for example). Default response behavior is stored in parent request class, but if you need to have your own custom response, you could override format_response function and code it according to your needs. 

## Builtin validators

Validators are stored in the abstract request class, and they are used to validating arguments passed to setter functions. The setter function needs to call one (or many) validator function to use this functionality. 

Here is the list of added validator functions:

`validate_stripe_id( $id, $prefixes = null )`

Used to validate stripe identifiers. Stripe id can be any stripe id like customer_id, charge_it, intent_id, … $prefix variable is used to set the pattern that will be used to validate the passed ID. If format is incorrect, the “Invalid_Request_Parameter_Exception” exception will be thrown with text that will say what went wrong

`validate_is_larger_then( $value_to_validate, $value_to_compare )`

Used to validate, is passed value larger the passed value to compare.

`validate_currency_code( string $currency_code )`

Used to validate is passed argument valid currency code by checking is passed currency supported by customer

`validate_extended_class( $child_class, string $parent_class )`

This validator is used during extending or applying filters. When a request class is extended via filters, the rule is that the new class that extends the original request class needs to be subclass of the original request class.

## Extensibility

Every request class can be modified or extended using filters. There is built in functionality to use that within request classes. One of the ways is to use the built-in static “extend” function. The recommended way to extend request or to hook is to use defined filters. When request method “send” is called (the method that actually sends the request to wcpay sever) the request filter name and filter arguments are passed to the send function. Te “send” function will apply passed filters and pass the arguments to it. To change behavior or to set some custom parameters, you could define your own filter, do your custom logic there. You could create your own request class by extending the request class you want to hook on, do some custom coding there and everything will work out of the box because of functionalities that are added to base request class. This approach makes things easier and you can easily change something in the codebase without worrying that you might break something somewhere else. You can create a filter and apply it when you need it, and set your own custom logic wherever you need.

## Testing

To mock request classes, the recommended way is to use the built-in mock_wcpay_request function. One of the approaches is to mock format_respomse function and send whatever response you might need. The examples will be shown below on how to mock response.  This function accepts 7 parameters. The first parameter is the request class you want to mock. That class can be class name of request you would like to mock or subclass of request you would like to mock. Subclasses are usually used when you would like to extend a request with another class by using filters.
The second argument is a number of API calls. This number tells how many API requests towards the server are expected to be executed. By default, this is set to one, but if you need to changed it you can pass any number you would like to. The good example is  when you want to be sure that no requests will be executed (like $this->never() in PHPUnit) or when you are executing same API request class multiple times (like retry mechanism).
The third argument is request class constructor ID. This id is used in request classes where you need to pass id or any identifier to wcpay server API. Most common example is when you want to update something or get something, and you need ID of that resource.
The fourth argument is the response you would like to have from wc pay server API. In most cases this is not needed, because you can mock format response function and set any response you would like to, but if you want to test format_response function, you can set response using this argument and test whatever scenario you want.
The last two arguments are WC Payments API Client and WC Payments HTTP interface. This is used to handle all request related task to wcpay server (like authentication, …). In most you don’t need to send your version of mocked classes here, but if you would like to test something inside these classes feel free to pass whatever mocked object you would like to.

## List of covered requests

Current WooCommerce payments client API has over 100 requests. In time, we are planning to cover all of them. Here is the list of requests we covered so far with 

- [Cancel_Intention](requests/class-cancel-intention.md)
- [Capture_Intention](requests/class-capture-intention.md)
- [Create_And_Confirm_Intention](requests/class-create-and-confirm-intention.md)
- [Create_And_Confirm_Setup_Intention](requests/class-create-and-confirm-setup-intention.md)
- [Create_Intention](requests/class-create-intention.md)
- [Create_Setup_Intention](requests/class-create-setup-intention.md)
- [Generic](requests/class-generic.md)
- [Get_Charge](requests/class-get-charge.md)
- [Get_Intention](requests/class-get-intention.md)
- [List_Authorizations](requests/class-list-authorizations.md)
- [List_Deposits](requests/class-list-deposits.md)
- [List_Disputes](requests/class-list-disputes.md)
- [List_Documents](requests/class-list-documents.md)
- [List_Transactions](requests/class-list-transactions.md)
- [Paginated](requests/class-paginated.md)
- [Update_Intention](requests/class-update-intention.md)
- [WooPay_Create_And_Confirm_Intention](requests/class-woopay-create-and-confirm-intention.md)
- [WooPay_Create_And_Confirm_Setup_Intention](requests/class-woopay-create-and-confirm-setup-intention.md)
- [WooPay_Create_Intent](requests/class-woopay-create-intent.md)

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
