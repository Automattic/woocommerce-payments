# WooCommerce Payments: Creating and extending server requests

1. [Creating new requests](#creating-new-requests)
    1. [Basic methods](#basic-methods)
    1. [Identifiers](#identifiers)
    1. [Setters](#setters)
    1. [Parameter definitions](#parameter-definitions)
        1. [Immutable parameters](#immutable-parameters)
    1. [Validators](#validators)
1. [Extending requests](#extending-requests)
    1. [Finding the definition](#1-look-for-the-definition)
    2. [Creating a new extended class](#2-extend-the-class)
    3. [Using the extended class](#3-replacing-the-class)
1. [Testing](#testing)


## Creating new requests

This is the anatomy of a request-specific class:

```php
namespace WCPay\Core\Server\Request;
use WCPay\Core\Server\Request;

class Update_Item extends Request {
	const DEFAULT_PARAMS   = [
		'name'   => '',
		'origin' => 'uknown',
	];

	const REQUIRED_PARAMS = [
		'name',
	];

	const IMMUTABLE_PARAMS = [
		'name',
	];

	protected function set_id( string $id ) {
		$this->validate_stripe_id( $id );
		$this->id = $id;
	}

	public function get_api(): string {
		return WC_Payments_API_Client::ITEMS_API . '/' . $this->id;
	}

	public function get_method(): string {
		return \Requests::POST;
	}

	public function set_name( string $name ) {
		$this->set_param( 'name', $name );
	}
}
```

### Basic methods

Each request class should define the otherwise abstract methods:

- `get_api()` prepares the URL for the request. It's preferred to use one of the class constants of `WC_Payments_API_Client`, optionally followed by any other parts of the URL (ex. `$this->id . '/capture'`).
- `get_method()` returns the HTTP method, preferably from the global `Requests` class.

### Identifiers

Requests, which require an ID must define the `set_id` method, which validates the ID, and stores it within `$this->id`.

Calling `Request::create( $id )` will automatically pass the identifier to the `set_id` method.

### Setters

A setter should be provided for each parameter of the request. Setters:

- Provide a way to **set a certain property**. The setter does not necessarily need to match the name of the property.
- **Provide typehints** by having the correct type and DocBlocks to allow IDEs to do their job.
- **Validate** the paramets. This can be done through custom functionality, or by using some of the [built-in validators, listed below](#validators).

Once the value is available and validated, it should be stored through `$this->set_param( $name, $value )`. By using the functionality of the main `Request` class for this, there is no need for additional definition of parameters, and they can safely be stored without allowing them to be modified later (unless needed).

### Parameter definitions

While none of those are required, parameter names can be included in a few special array constants:

- `DEFAULT_PARAMS` allow pre-defining values to specific parameters. This is also useful for parameters, which do not have a setter, but need to be included in the request, ex: The `confirm` paramter of `Create_and_Confirm_Intent` is always present by definition, but does not have a setter.
- `REQUIRED_PARAMS` should include all parameters, which must be present for the request to function.
- `IMMUTABLE_PARAMS` is a bit more special.

> 💡 All constants above are accummulated starting with the child class, and then the parent.
> Extended requests can add, but not remove parameters.

#### Immutable parameters

Those parameters can be set in the main piece of code where the request is created and prepared, but cannot be modified after `$request->send()` is called. At this point all attempts to change the value of an immutable parameter, either through its main setter, or through an extended request will cause an exception.

Example:

```php
class My_Request {
	const IMMUTABLE_PARAMS = [
		'name',
	];

	public function set_name( $name ) {
		$this->set_param( 'name', $name );
	}
}

add_filter( 'wcpay_my_request', function ( $request ) {
	$request->set_name( 'Hans' ); // <-- this will throw an `Immutable_Parameter_Exception`
} );

$request = My_Request::create();
$request->set_name( 'John' );
$request->send( 'wcpay_my_request );
```

### Validators

Validators are stored in the abstract `Request` class, and they are used to validate arguments passed to setter methods. The setter method needs to call one (or many) validation method to use this functionality.

- `validate_stripe_id( $id, $prefixes = null )` is used to validate Stripe IDs. Provide the ID, and optionally either a single prefix, on array of prefixes. This method can be used for all IDs, which generally follow the format `type_HASH`.
- `validate_is_larger_then( $value_to_validate, $value_to_compare )`
- `validate_currency_code( string $currency_code )`
- `validate_date( string $date, string $format = 'Y-m-d H:i:s' )`

All of those validators would throw an `Invalid_Request_Parameter_Exception` exception if the value is not in the correct format.

Example:

```php
/**
 * Sets the deposit ID.
 * 
 * @param  string $deposit_id                  The deposit ID, including. the `po_` prefix.
 * @throws Invalid_Request_Parameter_Exception When the provided ID is not valid.
 */
public function set_deposit( string $deposit_id ) {
	$this->validate_stripe_id( $deposit_id, 'po' );
	$this->set_param( 'deposit', $deposit_id );
}
```

## Extending requests

Every request class can be extended, and replaced using filters. The important addition with requests is the static `extend()` method, which can be used on child classes to extend the parent request __object__ once it already exists.

### 1. Look for the definition

Request classes can be extended as any other PHP class. Let's use the existing `Create_and_Confirm_Intention` and `WooPay_Create_and_Confirm_Intention` requests as an example. Here is how `Create_and_Confirm_Intention` is used in the gateway:

```php
$request = Create_And_Confirm_Intention::create();
// Call all necessary setters...
$intent = $request->send( 'wcpay_create_intention_request', $payment_information );
```

### 2. Extend the class

It can be easily extended:

```php
class WooPay_Create_and_Confirm_Intention extends Create_and_Confirm_Intention {
	public function set_is_platform_payment_method() {
		$this->set_param( 'is_platform_payment_method', true );
	}
}
```

### 3. Replacing the class
 
The important part is how the new class is used. Instead of replacing `Create_and_Confirm_Intention` where it is used, please use the provided filter (`wcpay_create_intention_request` in this case) instead:


```php
function replace_request( Create_and_Confirm_Intention $base_request, Payment_Information $payment_information ) {
	$request = WooPay_Create_and_Confirm_Intention::extend( $base_request );
	$request->set_is_platform_payment_method();
	return $request;
}
	
add_filter( 'wcpay_create_intention_request', 'replace_request', 10, 2 );
```

Notice how `WooPay_Create_and_Confirm_Intention::extend()` is called here, and the provided argument is an instance of `Create_and_Confirm_Intention`. This mechanism copies the parameters of the existing request into the new one, but keeps them protected.

#### Immutable parameters

Even though the request is now an instance of another class, immutable parameters cannot be changed:

```php
function replace_request( Create_and_Confirm_Intention $base_request, Payment_Information $payment_information ) {
	$request = WooPay_Create_and_Confirm_Intention::extend( $base_request );
	$request->set_amount( 300 ); // <-- this will throw an exception.
	return $request;
}
```

## Testing

To mock request classes, the recommended way is to use the built-in `mock_wcpay_request` function. One of the approaches is to mock `format_respomse` function and send whatever response you might need. The examples will be shown below on how to mock response.  This function accepts 7 parameters. The first parameter is the request class you want to mock. That class can be class name of request you would like to mock or subclass of request you would like to mock. Subclasses are usually used when you would like to extend a request with another class by using filters.

The second argument is a number of API calls. This number tells how many API requests towards the server are expected to be executed. By default, this is set to one, but if you need to changed it you can pass any number you would like to. The good example is  when you want to be sure that no requests will be executed (like $this->never() in PHPUnit) or when you are executing same API request class multiple times (like retry mechanism).

The third argument is request class constructor ID. This id is used in request classes where you need to pass id or any identifier to wcpay server API. Most common example is when you want to update something or get something, and you need ID of that resource.

The fourth argument is the response you would like to have from wc pay server API. In most cases this is not needed, because you can mock format response function and set any response you would like to, but if you want to test format_response function, you can set response using this argument and test whatever scenario you want.

The last two arguments are WC Payments API Client and WC Payments HTTP interface. This is used to handle all request related task to wcpay server (like authentication, …). In most you don’t need to send your version of mocked classes here, but if you would like to test something inside these classes feel free to pass whatever mocked object you would like to.
