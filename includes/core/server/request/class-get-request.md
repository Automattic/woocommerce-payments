
# `Get_Request` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_Request` class is used to construct the basic and simple request that uses HTTP GET method.

## Parameters

When creating `Get_Request` requests, the ID parameter could be passed to the `::create()` method. It's optional argument and used when you want to pass some resource ID to the request.


| Parameter | Setter                   | Immutable | Required | Default value |
|-----------|--------------------------|:---------:|:--------:|:-------------:|
| `id`      | `set_id( $id )`          |    Yes    |    No    |       -       |
| `api`     | `set_api( string $api )` |     -     |   Yes    |       -       |



## Examples:

```php
$request = Request::get( ('deposits', $id );
$request->assign_hook( 'wcpay_get_deposit_request' );
$request->send();
```

```php
$request = Request::get( ('deposits' ); // Without ID argument.
$request->assign_hook( 'wcpay_get_deposits_request' );
$request->send();
```

You could also create request with the following code, but it is not recommended.
```php
$request = Get_Request::create( $id );
$request->set_method ('deposits')
$request->assign_hook( 'wcpay_get_deposit_request' );
$request->send();
```
