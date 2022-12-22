# WooCommerce Payments Server Requests

This document outlines the concepts and implementation of WooCommerce Payments server request classes.

The purpose of request classes is to provide an easy way to send API requests towards WooCommerce Payments Server, where type safety, validation and builder pattern is used to built requests towards WooCommerce Payments Server.

To make it easier for other parties to use it and extend it, every request class has its own filter that can be hooked on, and you could change the behavior or even set your own parameters while maintain all functionality from the original request class.

## Usage

In general, to use any specific request class to make a request, you need to:

1. Create the request.
2. Prepare the request by calling all necessary setters (mainly for `POST` requests).
3. Send the request.

### Using `GET` and `DELETE` requests

Requests to retrieve or delete items should be performed similarly to this:

```php
<?php
use WCPay\Core\Server\Request\Get_Intention;

$intention_id = $this->order_service->get_intent_id_for_order( $order );
$request      = Get_Intention::create( $intention_id );

/**
 * Sends a request to retrieve an intention.
 * 
 * @param WC_Order $order The order, which the intent is associated with.
 */
$intention = $request->send( 'my_get_intention_request', $order );
```

Highlights from this example:

1. For `GET` requests the item identifier is a required parameter for the `::create()` method.
2. Additional parameters should be providable only through setters (see examples with `POST` requests below).
3. Whenever sending the request, it is required to provide a filter.

### Using `POST` requests

Requests to create/update items should look similarly to this:

```php
<?php
use WCPay\Core\Server\Request\Create_Intention;

$request = Create_Intention::create();
$request->set_amount( WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
$request->set_currency( $order->get_currency() );
$request->set_payment_method( $payment_information->get_payment_method() );

/**
 * Sending a request to create and confirm a payment intention.
 * 
 * @param WC_Order            $order               The order which the intention belongs to. 
 * @param Payment_Information $payment_information Prepared payment information from the gateway.
 */
$intention = $request->send( 'wcpay_create_intention_request', $order, $payment_information );
```

### Generic requests

[➡️ Generic Requests Documentation](request/class-generic.md)

The `WCPay\Core\Server\Request\Generic` class is available to use during development without needing to create a new request class.

```php
use WCPay\Core\Server\Request\Generic;

$request = new Generic(
	'payment_intentions',
	\Requests::POST,
	[
		'amount' => 300
	]
);
$intention = $request->send( 'custom_create_intention_request' );
```

However, once the rest of the related development is finished, please create a new request class before merging.

### Providing and using filters

Request classes are build from the ground up with the idea of being safely extendable. Every time a request is sent, it is mandatory to provide at least a filter, and potentially arguments for that filter, in order to allow others to modify it.

Filters in request work similarly to generic WordPress filter, with the difference that they are provided through the request. This allows protected (immutable) parameters to remain protected, and slightly decreases the overhead of checking the returned request.

#### Providing filters

It is mandatory to provide a filter when sending each request. When doing so, please try to provide enough context for the filter, but do not provide parameters, which are already in the filter.

> The `->send()` method of any request should be documented similarly to any other WordPress filter/action.

If you are using the same hook name as another instance, please use the same parameters.

Here is a **good example**:

```php
/**
 * Sending a request to create and confirm a payment intention.
 * 
 * @param WC_Order            $order               The order which the intention belongs to. 
 * @param Payment_Information $payment_information Prepared payment information from the gateway.
 */
$intention = $request->send( 'wcpay_update_intention_request', $order, $payment_information );
```

- This example includes everything needed in the `$order` and `$payment_information` parameters, and they are documented.
- The hook name is prefixed with `wcpay_`.

Here is a **bad example**:

```php
$intention = $request->send( 'update_request', $intention_id );
```

- This example uses `update_request`, which could appear in other hooks.
- The relevant order and payment information objects are not present.
- `$intention_id` is redundant, as it's already available though the request: `$request->get_id()`.
- There is no PHPDoc comment for the request, leaving others guessing what types to expect.

#### Using filters

Based on the good example above, a request can be modified in the following way:

```php
add_filter( 'wcpay_update_intention_request', 'my_update_intention_callback', 10, 3 );
function my_update_intention_callback( $request, WC_Order $order, \WCPay\Payment_Information $payment_information ) {
	$request->set_metadata( [] );
	$request->set_amount( 300 ); // Do not try this, `amount` is protected, and cannot be modified through filters.
	return $request;
}
```

Note that you need to increase the callback parameter count by 1 in order to receive both the request, and all additional context.

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

## Exceptions
