# WooCommerce Payments Server Requests

This document outlines the concepts and implementation of WooCommerce Payments server request classes.

The purpose of request classes is to provide an easy and structured way to send API requests towards WooCommerce Payments Server, where type safety, validation and builder pattern is used to built requests towards the server.

To make it easier for other parties to use it and extend it, every request class has its own filter that can be hooked on, and you could change the behavior or even set your own parameters while maintain all functionality from the original request class.

1. [Usage](#usage)
    1. [Using `GET` and `DELETE` requests](#using-get-and-delete-requests)
    1. [Using `POST` requests](#using-post-requests)
    1. [Generic requests](#generic-requests)
1. [Providing and using filters](#providing-and-using-filters)
    1. [Providing filters](#providing-filters)
    1. [Using filters](#using-filters)
1. [Available requests](#available-requests)
1. [Responses](#responses)
1. [Creating new requests and extending existing ones](#creating-new-requests-and-extending-existing-ones)

## Usage

In general, to use any specific request class to make a request, you need to:

1. Create the request.
2. Prepare the request by calling all necessary setters (mainly for `POST` requests).
3. Send the request.

#### Using `GET` and `DELETE` requests

Requests to retrieve or delete items should be performed similarly to this:

```php
<?php
use WCPay\Core\Server\Request\Get_Intention;

$intention_id = $this->order_service->get_intent_id_for_order( $order );
$request      = Get_Intention::create( $intention_id );
$request->assign_hook( 'my_get_intention_request', $order );

/**
 * Sends a request to retrieve an intention.
 * 
 * @param WC_Order $order The order, which the intent is associated with.
 */
$intention = $request->send();
```

Highlights from this example:

1. For `GET` requests the item identifier is a required parameter for the `::create()` method.
2. Additional parameters should be providable only through setters (see examples with `POST` requests below).
3. Whenever sending the request, it is required to provide a filter.

#### Using `POST` requests

Requests to create/update items should look similarly to this:

```php
<?php
use WCPay\Core\Server\Request\Create_Intention;

$request = Create_Intention::create();
$request->set_amount( WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ) );
$request->set_currency( $order->get_currency() );
$request->set_payment_method( $payment_information->get_payment_method() );
$intention = $request->send();
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
$intention = $request->send();
```

However, once the rest of the related development is finished, please create a new request class before merging.

## Providing and using filters

Request classes are build from the ground up with the idea of being safely extendable. Every time a request is sent, it is mandatory to provide at least a filter, and potentially arguments for that filter, in order to allow others to modify it.

Filters in request work similarly to generic WordPress filter, with the difference that they are provided through the request. This allows protected (immutable) parameters to remain protected, and slightly decreases the overhead of checking the returned request.

### Providing filters

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
$request->assign_hook( 'wcpay_update_intention_request' );
$request->set_hook_args( $order, $payment_information )
$intention = $request->send();
```

- This example includes everything needed in the `$order` and `$payment_information` parameters, and they are documented.
- The hook name is prefixed with `wcpay_`.

Here is a **bad example**:

```php
$request->assign_hook('update_request', $intention_id );

$intention = $request->send();
```

- This example uses `update_request`, which could appear in other hooks.
- The relevant order and payment information objects are not present.
- `$intention_id` is redundant, as it's already available though the request: `$request->get_id()`.
- There is no PHPDoc comment for the request, leaving others guessing what types to expect.

### Using filters

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

## Available requests

Currently the WooCommerce Payments API client contains around 100 requests. The plan is to cover all of them in time, in the meantime here is a list of the existing requests:

- Intentions
	- [Cancel_Intention](request/class-cancel-intention.md)
	- [Capture_Intention](request/class-capture-intention.md)
	- [Create_And_Confirm_Intention](request/class-create-and-confirm-intention.md)
	- [Create_And_Confirm_Setup_Intention](request/class-create-and-confirm-setup-intention.md)
	- [Create_Intention](request/class-create-intention.md)
	- [Create_Setup_Intention](request/class-create-setup-intention.md)
	- [Get_Intention](request/class-get-intention.md)
	- [Update_Intention](request/class-update-intention.md)
- [Get_Charge](request/class-get-charge.md)
- Paginated lists (mainly used for the REST API):
	- [List_Authorizations](request/class-list-authorizations.md)
	- [List_Deposits](request/class-list-deposits.md)
	- [List_Disputes](request/class-list-disputes.md)
	- [List_Documents](request/class-list-documents.md)
	- [List_Transactions](request/class-list-transactions.md)
  - [List_Fraud_Outcome_Transactions](request/class-list-fraud-outcome-transactions.md)
- WooPay-specific
	- [WooPay_Create_Intent](request/class-woopay-create-intent.md)
	- [WooPay_Create_And_Confirm_Intention](request/class-woopay-create-and-confirm-intention.md)
	- [WooPay_Create_And_Confirm_Setup_Intention](request/class-woopay-create-and-confirm-setup-intention.md)

## Responses

Please check the documentation of specific requests to see their particular response types and formats.

Unless specified otherwise, most requests will return a `WCPay\Core\Server\Reponse` object, which can be treated as an array (implements `ArrayAccess`), representing the server response. Additionally to that, specific requests might have additional getters and validators.

## Creating new requests and extending existing ones

Please refer to [contributor docs](CONTRIBUTING.md).
