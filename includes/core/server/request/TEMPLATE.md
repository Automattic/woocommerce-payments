# `%class_name%` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../requests.md)

## Description

The `WCPay\Core\Server\Request\%class_name%` class is used to construct the request for %human_name%.

## Parameters

%id%
%parameters%

## Filter

When using this request, provide the following filter and arguments:

- Name: `%filter%`
- Arguments: %filters_args%

## Example:

```php
$request = %class_name%::create(%constructor_arg%);%setters%
$request->send( '%filter%'%filter_args_inline% );
```
