# Creating new request classes for WooCommerce Payments

1. [Creating new requests](#creating-new-requests)
    1. [Basic methods](#basic-methods)
    1. [Identifiers](#identifiers)
    1. [Setters](#setters)
    1. [Parameter definitions](#parameter-definitions)
        1. [Immutable parameters](#immutable-parameters)
    1. [Validators](#validators)
1. [Extending requests during runtime](#extending-requests-during-runtime)
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
$request->assign_hook( 'wcpay_my_request' );
$request->send();
```

### Validators

Validators are stored in the abstract `Request` class, and they are used to validate arguments passed to setter methods. The setter method needs to call one (or many) validation method to use this functionality.

- `validate_stripe_id( $id, $prefixes = null )` is used to validate Stripe IDs. Provide the ID, and optionally either a single prefix, on array of prefixes. This method can be used for all IDs, which generally follow the format `type_HASH`.
- `validate_is_larger_than( $value_to_validate, $value_to_compare )`
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

## Extending requests during runtime

Every request class can be extended, and replaced using filters. The important addition with requests is the static `extend()` method, which can be used on child classes to extend the parent request __object__ once it already exists.

### 1. Look for the definition

Request classes can be extended as any other PHP class. Let's use the existing `Create_and_Confirm_Intention` and `WooPay_Create_and_Confirm_Intention` requests as an example. Here is how `Create_and_Confirm_Intention` is used in the gateway:

```php
$request = Create_And_Confirm_Intention::create();
$request->set_hook_args( $payment_information );
// Call all necessary setters...
$intent = $request->send();
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

To mock request classes, the recommended way is to use the built-in `mock_wcpay_request` function of `WCPAY_UnitTestCase`.

```php
function mock_wcpay_request(
  // The request class you want to mock.
  string $request_class,

  // How many of the same API requests towards the server are expected to be executed. The default number is set to 1 and in the most cases you won't need to update this value
  // Could be `0` to simulate `->never()`.
  // If you have multiple execution of same request class (like retry mechanism or reusing the same request class instance), update this variable and match it with a number of executed API calls towards server.
  int $total_api_calls = 1,

  // ID of the item, which should be updated/retrieved, only needed for certain classes.
  string $request_class_constructor_id = null,

  // A response to be returned from the API.
  mixed $response = null
) : Request;
```

This will expect the request to be called `$total_api_calls` number of times. In addition, with this approach you can only mock the necessary request parameters. Here are some examples:

__Standard mock__

```php
$mock_response = [
    // add mock response here...
];
$request = $this->mock_wcpay_request( List_Documents::class, 1, null, $mock_response );
$request
  ->expects( $this->once() )
  ->method( 'set_type_is' )
  ->with( 'bill' );
```

__A request, which should not be executed__

```php
$this->mock_wcpay_request( Create_Intention::class, 0 );
```

__Throw an exception__

```php
$request = $this->mock_wcpay_request( Create_Intention::class );;
$request
  ->expects( $this->once() )
  ->method( 'format_response' )
  ->will( $this->throwException( new API_Exception( /* ... */ ) ) );
```

 __Requests with custom responses__

Some request classes will try to parse and format the response they receive, in which case mocking it through the `$response_parameter` of `mock_wcpay_request` will not work. In those cases, you need to mock the `format_response` method:

```php
$request = $this->mock_wcpay_request( Create_Intention::class );;
$request
  ->expects( $this->once() )
  ->method( 'format_response' )
  ->willReturn( $mock_intention );
```
