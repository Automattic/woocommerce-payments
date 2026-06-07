# Express Checkout Custom Checkout Fields

WooPayments Express Checkout sends eligible classic checkout form fields through the Store API extensions payload before placing an Apple Pay or Google Pay order.

Fields registered through the `woocommerce_checkout_fields` filter are validated for `required` server-side and saved to order meta by default using their checkout field keys.

Required validation assumes the registered field is present on the checkout form whenever it is required. If a required field is conditionally hidden, make sure the condition also updates the field's required state, or handle that field through the validation hook below.

Fields rendered directly into the checkout form, for example with `woocommerce_after_order_notes`, are included in the WooPayments extension payload but are not automatically validated or saved. Use the WooPayments hooks below when those fields need Express Checkout support.

## Validate A Custom Field

```php
add_action(
	'wcpay_express_checkout_after_custom_fields_validation',
	function ( array $custom_checkout_data, WP_Error $errors ) {
		if ( empty( $custom_checkout_data['my_custom_field'] ) ) {
			$errors->add(
				'my_custom_field_required',
				__( 'My custom field is required.', 'my-text-domain' )
			);
		}
	},
	10,
	2
);
```

## Save A Custom Field

```php
add_action(
	'wcpay_express_checkout_update_custom_fields_order_meta',
	function ( int $order_id, array $custom_checkout_data ) {
		if ( ! isset( $custom_checkout_data['my_custom_field'] ) ) {
			return;
		}

		update_post_meta(
			$order_id,
			'my_custom_field',
			sanitize_text_field( wp_unslash( $custom_checkout_data['my_custom_field'] ) )
		);
	},
	10,
	2
);
```
