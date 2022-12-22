# WooCommerce Payments Server Requests: Creating and extending requests

## Creating request class

A new abstract request class have been added that outlines functionalities that are standard for each request class. New defined parameters constants (`IMMUTABLE_PARAMS`, `REQUIRED_PARAMS`, `DEFAULT_PARAMS`) holds some basic functionality for request parameters. Immutable parameters constant is sued to maintain immutability when request is extended or altered via filters. One good example is “amount” parameter in any intent API request calls, where “amount” cannot be changed in any filters. Default parameters constant is used to hold default value for request parameters. Using this approach will make building a request easier by not calling setters for default parameters. Required parameters constant holds a list of parameters that are required. This constant is used during execution of the request, where each parameter is checked and validated. 
To set request parameters, each request class has its own setter functions. Some of these functions have their own validators. Using validators will force us to pass variables that are in correct type and in correct format.
Some request classes uses ID to send request. For example, update intent requests needs to know the ID of intent it needs to update. You can pass ID to request class and request class will know how to handle it.
To create a request, use a static function called “create” and pass ID if needed. All dependencies are already taken care of. More examples will be provided in this document, where every request will be displayed with built steps.

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
