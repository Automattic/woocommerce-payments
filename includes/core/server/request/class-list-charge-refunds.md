# `List_Charge_Refunds` request class

[ℹ️ This document is a part of __WooCommerce Payments Server Requests__](../README.md)

## Description

The `WCPay\Core\Server\Request\List_Charge_Refunds` class is used to construct the request for listing refunds of a specific charge.

## Parameters


| Parameter | Setter                         | Immutable | Required | Default value |
|-----------|--------------------------------|:---------:|:--------:|:-------------:|
| `charge`  | `set_charge( string $charge_id )` |    Yes    |   Yes    |     None      |
| `test_mode` | `set_test_mode( bool $test_mode )` | No | No | Current store mode |
| `starting_after` | `set_starting_after( string $refund_id )` | No | No | Omitted |
| `expand` | `set_expand_balance_transactions()` | No | No | Omitted |
| `include_reporting_context` | `set_include_reporting_context()` | No | No | Omitted |
| `limit`   | `set_limit( int $limit )`      |    No     |    No    |      100      |


## Filter

- Name: `wcpay_list_charge_refunds_request`
- Arguments: None

## Example:

```php
$request = List_Charge_Refunds::create();
$request->set_charge( 'ch_id' );
$request->set_limit( 100 ); // It is not required. You can also skip this setter.
$request->send();
```

For historical retrieval, explicitly set the original payment mode before sending the request. Omitting the setter preserves the existing store-mode behavior. This option does not bind an account or establish that the returned refund history is complete; callers must verify the server response and pagination contract before reporting complete totals.

Historical pagination and balance expansion require server support. The expansion setter requests only `data.balance_transaction` and `data.failure_balance_transaction`; ordinary callers retain the existing response shape. Use the last returned refund ID as the next cursor, retaining the same charge and original test/live mode. Inspect `has_more`, reject repeated/non-progressing pages, and keep retrieval incomplete when required expanded objects are missing. A server that silently ignores the new parameters must not produce a complete historical total. These setters do not implement a collector, qualify currency amounts, pin an account, or persist refund history.

`set_include_reporting_context()` opts into the proposed server provenance envelope, `wcpay_reporting_context`. It requires the companion server change. Historical consumers must compare its version, account ID, site ID and boolean test mode with trusted saved context on every page before accepting records. Missing or mismatched provenance leaves collection incomplete. Requesting the envelope does not itself select a historical account.
