# Order Metadata Access in WooCommerce Payments

## Purpose

To improve the extensibility and stability of WooCommerce Payments, all WooCommerce Payment related order metadata should be accessible in a clear and stable manner. We would like to prevent accessing metadata directly. By implementing this, changes in datastructure will not affect extensions and extensions will be able to reliably access the data. 

## Accessing Metadata

`WC_Payments_Order_Service` contains the getters and setters which can be used to access and update WooCommerce Payments Order metadata. Following are the metadata and methods provided:
- `_intent_id`
	- `get_intent_id_for_order( mixed $order ) : string`
	- `set_intent_id_for_order( WC_Order $order, string $intent_id )`
- `_payment_method_id`
	- `get_payment_method_id_for_order( mixed $order ) : string`
	- `set_payment_method_id_for_order( mixed $order, string $payment_method_id )`
- `_charge_id`
	- `get_charge_id_for_order( mixed $order ) : string`
	- `set_charge_id_for_order( mixed $order, string $charge_id )`
- `_intention_status`
	- `get_intention_status_for_order( mixed $order ) : string`
	- `set_intention_status_for_order( mixed $order, string $intention_status )`
- `_stripe_customer_id`
	- `get_customer_id_for_order( mixed $order ) : string`
	- `set_customer_id_for_order( mixed $order, string $customer_id )`
- `_wcpay_intent_currency`
	- `get_wcpay_intent_currency_for_order( mixed $order ) : string`
	- `set_wcpay_intent_currency_for_order( mixed $order, string $wcpay_intent_currency )`
- `_wcpay_refund_id`
	- `get_wcpay_refund_id_for_order( mixed $order ) : string`
	- `set_wcpay_refund_id_for_order( mixed $order, string $wcpay_refund_id )`
- `_wcpay_refund_status`
	- `get_wcpay_intent_currency_for_order( mixed $order ) : string`
	- `set_wcpay_intent_currency_for_order( mixed $order, string $wcpay_refund_status )`
- `_wcpay_fraud_outcome_status`
	- `get_fraud_outcome_status_for_order( mixed $order ) : string`
	- `set_fraud_outcome_status_for_order( mixed $order, string $fraud_outcome_status )`
- `_wcpay_fraud_meta_box_type`
	- `get_fraud_meta_box_type_for_order( mixed $order ) : string`
	- `set_fraud_meta_box_type_for_order( mixed $order, string $fraud_meta_box_type )`

## Hooks

- Hooks can be used to notify consumers about updates to metadata. The following hooks are provided at this time:

	- `wcpay_order_intent_id_updated` action hook is triggered when `_intent_id` is updated.
	- `wcpay_order_payment_method_id_updated` action hook is triggered when `_payment_method_id` is updated.

## Adding Metadata

For any WCPay order related metadata, add the getters and setters to `WC_Payments_Order_Service`. Access the metadata in other parts of the code, and in dependent extensions using these getters and setters. Provide action hooks as required.