/* global jQuery */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { normalizeShippingAddress, getExpressCheckoutData } from '.';

const updateShortcodeField = ( formSelector, fieldName, value ) => {
	const field = document.querySelector(
		`${ formSelector } [name="${ fieldName }"]`
	);
	if ( ! field ) {
		return;
	}
	field.value = value;
	jQuery( field ).trigger( 'change' ).trigger( 'close' );
};

export const updateBlocksShippingUI = ( eventAddress ) => {
	wp?.data
		?.dispatch( 'wc/store/cart' )
		?.setShippingAddress( normalizeShippingAddress( eventAddress ) );
};

export const updateShortcodeShippingUI = ( eventAddress ) => {
	const context = getExpressCheckoutData( 'button_context' );
	const address = normalizeShippingAddress( eventAddress );

	const keys = [ 'country', 'state', 'city', 'postcode' ];

	if ( context === 'cart' ) {
		keys.forEach( ( key ) => {
			if ( address[ key ] ) {
				updateShortcodeField(
					'form.woocommerce-shipping-calculator',
					`calc_shipping_${ key }`,
					address[ key ]
				);
			}
		} );
		document
			.querySelector(
				'form.woocommerce-shipping-calculator [name="calc_shipping"]'
			)
			?.click();
	} else if ( context === 'checkout' ) {
		keys.forEach( ( key ) => {
			if ( address[ key ] ) {
				updateShortcodeField(
					'form.woocommerce-checkout',
					`billing_${ key }`,
					address[ key ]
				);
			}
		} );
	}
};
