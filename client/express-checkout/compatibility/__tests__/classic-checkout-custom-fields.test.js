/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import '../classic-checkout-custom-fields';

const EXTENSION_NAMESPACE = 'woocommerce-payments/express-checkout';

describe( 'Classic checkout custom fields compatibility', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		global.wcpayExpressCheckoutParams = {
			button_context: 'checkout',
			custom_checkout_fields: {
				my_field_name: {
					type: 'text',
				},
				gift_wrap: {
					type: 'checkbox',
				},
				newsletter_signup: {
					type: 'checkbox',
				},
				delivery_preference: {
					type: 'radio',
				},
				order_note: {
					type: 'textarea',
				},
			},
		};
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete global.wcpayExpressCheckoutParams;
	} );

	it( 'adds checkout form custom field values to the Store API extensions payload', () => {
		document.body.innerHTML = `
			<form name="checkout">
				<input name="my_field_name" value="A required value" />
				<input type="checkbox" name="gift_wrap" value="yes" checked />
				<input type="checkbox" name="newsletter_signup" value="yes" />
				<input type="radio" name="delivery_preference" value="morning" />
				<input type="radio" name="delivery_preference" value="evening" checked />
				<textarea name="order_note">Please leave it at the door.</textarea>
			</form>
		`;

		const extensionData = applyFilters(
			'wcpay.express-checkout.cart-place-order-extension-data',
			{
				'example/existing-extension': {
					foo: 'bar',
				},
			}
		);

		expect( extensionData ).toMatchObject( {
			'example/existing-extension': {
				foo: 'bar',
			},
			[ EXTENSION_NAMESPACE ]: {
				custom_checkout_data: JSON.stringify( {
					my_field_name: 'A required value',
					gift_wrap: 'yes',
					newsletter_signup: '',
					delivery_preference: 'evening',
					order_note: 'Please leave it at the door.',
				} ),
			},
		} );
	} );

	it( 'does not add checkout form custom field values outside checkout context', () => {
		global.wcpayExpressCheckoutParams.button_context = 'cart';
		document.body.innerHTML = `
			<form name="checkout">
				<input name="my_field_name" value="A cart page value" />
			</form>
		`;

		const existingExtensionData = {
			'example/existing-extension': {
				foo: 'bar',
			},
		};

		const extensionData = applyFilters(
			'wcpay.express-checkout.cart-place-order-extension-data',
			existingExtensionData
		);

		expect( extensionData ).toBe( existingExtensionData );
	} );
} );
