# `Get_Charge` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\Get_Charge` class is used to construct the request for retrieving a charge.

## Parameters

When creating `Get_Charge` requests, the item ID must be provided to the `::create()` method. The identifier must use the `ch_XXX` or `py_XXX` format.

For historical retrieval, `set_test_mode( bool $test_mode )` explicitly selects the original payment mode for this request. It sends `test_mode=1` for test payments and `test_mode=0` for production payments without changing the global WooPayments mode. When omitted, the existing API-client default applies. Use the saved payment mode; an unknown mode should not be guessed from the store’s current setting.

## Filter

When using this request, provide the following filter and arguments:

- Name: `wcpay_get_charge_request`
- Arguments: None or charge id.

## Example:

```php
$request = Get_Charge::create( $id );
$request->send();
```

## Historical retrieval

```php
$request = Get_Charge::create( $charge_id );
$request->set_test_mode( $saved_payment_mode === 'test' );
$charge = $request->send();
```

Validate that the response belongs to the expected charge and mode before using its amounts. A successful request does not guarantee an expanded balance transaction, complete fee/net fields, or complete order payment history. Captured amounts, refunds, fees and bank payouts have different meanings. This request does not reconcile a bank receipt or convert an account-currency amount into another reporting currency.

`set_include_reporting_context()` requests the proposed server `wcpay_reporting_context` envelope (version, account ID, site ID and boolean test mode). It is omitted by default and requires the companion server change. Consumers must verify it against trusted saved context before using primary charge evidence. This opt-in does not select a historical account or establish provenance for separately enriched fields. The unregistered captured-payment recovery prototype requires trusted expected context and compares this envelope before passing a charge to the store. The snapshot store persists verified retrieval context in its revision hash and requires matching context for subsequent reads and writes. Establishing trusted queue-time context and its relationship to the original payment account remains separate lifecycle integration work.
