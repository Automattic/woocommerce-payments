# `Activate_PM_Promotion` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Activate_PM_Promotion` class is used to construct the request for activating a payment method (PM) promotion. When a promotion is activated, the associated payment method discount is applied to the merchant's account.

## Parameters

When creating `Activate_PM_Promotion` requests, the promotion ID must be provided to the `::create()` method. The identifier should be in the format used by the promotions system (e.g., `klarna-2026-promo__spotlight`).

There are no additional parameters for this request.

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_activate_pm_promotion_request`
- Arguments: None required (the promotion ID is available via `$request->get_id()`)

## Example

```php
use WCPay\Core\Server\Request\Activate_PM_Promotion;

$request = Activate_PM_Promotion::create( $promotion_id );
$request->assign_hook( 'wcpay_activate_pm_promotion_request' );
$response = $request->send();
```

## Related

- [`Get_PM_Promotions`](class-get-pm-promotions.md) - Retrieves available PM promotions from the server