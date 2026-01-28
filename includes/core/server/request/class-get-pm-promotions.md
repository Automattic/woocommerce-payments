# `Get_PM_Promotions` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_PM_Promotions` class is used to construct the request for retrieving available payment method (PM) promotions from the WooCommerce Payments server. These promotions offer merchants discounts on processing fees for specific payment methods.

## Parameters

No ID is required for creation. Store context can be provided via the `set_store_context_params()` method.

### Available Methods

| Method | Description |
|--------|-------------|
| `set_store_context_params( array $context )` | Attaches store context (dismissals, locale, etc.) to the request |

### Store Context Parameters

The `set_store_context_params()` method accepts an array with the following keys:

| Key | Type | Description |
|-----|------|-------------|
| `dismissals` | `array` | Map of dismissed promotion IDs to timestamps |
| `locale` | `string` | Store locale (e.g., `en_US`) |

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_get_pm_promotions_request`
- Arguments: None required

## Response

This request returns the raw response (including headers) to allow access to caching directives like `cache-for`.

## Example

```php
use WCPay\Core\Server\Request\Get_PM_Promotions;

$store_context = [
    'dismissals' => [ 'promo1__spotlight' => 1234567890 ],
    'locale'     => get_locale(),
];

$request = Get_PM_Promotions::create();
$request->set_store_context_params( $store_context );
$request->assign_hook( 'wcpay_get_pm_promotions_request' );
$response = $request->handle_rest_request();
```

## Related

- [`Activate_PM_Promotion`](class-activate-pm-promotion.md) - Activates a PM promotion