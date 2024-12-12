/* global jQuery */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { normalizeShippingAddress, getExpressCheckoutData } from './';

export const updateShortcodeShippingUI = ( eventAddress ) => {
	const context = getExpressCheckoutData( 'button_context' );
	const address = normalizeShippingAddress( eventAddress );

	const keys = [ 'country', 'state', 'city', 'postcode' ];

	if ( context === 'cart' ) {
		keys.forEach( ( key ) => {
			const field = document.querySelector(
				`form.woocommerce-shipping-calculator [name="calc_shipping_${ key }"]`
			);
			if ( address[ key ] && field ) {
				field.value = address[ key ];
				if ( [ 'country', 'state' ].includes( key ) ) {
					jQuery( field ).trigger( 'change' ).trigger( 'close' );
				} else {
					field.dispatchEvent( new Event( 'change' ) );
				}
			} else {
				console.error(
					`form.woocommerce-shipping-calculator [name="calc_shipping_${ key }"]`
				);
				console.error( address[ key ] );
			}
		} );
		document
			.querySelector(
				'form.woocommerce-shipping-calculator [name="calc_shipping"]'
			)
			?.click();
	} else if ( context === 'checkout' ) {
		// TODO: because of select2, we will need to just update the fields when the operation is cancelled...
		keys.forEach( ( key ) => {
			const field = document.querySelector(
				`form.woocommerce-checkout [name="billing_${ key }"]`
			);
			if ( address[ key ] && field ) {
				field.value = address[ key ];

				if ( [ 'country', 'state' ].includes( key ) ) {
					jQuery( field ).trigger( 'change' ).trigger( 'close' );
				} else {
					field.dispatchEvent( new Event( 'change' ) );
				}
			} else {
				console.error(
					`form.woocommerce-checkout [name="billing_${ key }"]`
				);
				console.error( address[ key ] );
			}
		} );
	}
};
