# `Get_Request` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\Get_Request` class is used to construct the basic and simple request that uses HTTP GET method.

## Parameters

When creating `Get_Request` requests, the ID parameter could be passed to the `::create()` method. It's optional argument and used when you want to pass some resource ID to the request.


| Parameter | Setter                   | Immutable | Required | Default value |
|-----------|--------------------------|:---------:|:--------:|:-------------:|
| `id`      | `set_id( $id )`          |    Yes    |    No    |       -       |
| `api`     | `set_api( string $api )` |     -     |   Yes    |       -       |



## Example:

```php
$request = Get_Request::create( $id );
$request->set_method ('deposits')
$request->send( 'wcpay_get_deposits_request' );
```
