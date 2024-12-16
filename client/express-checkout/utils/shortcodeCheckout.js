/* global jQuery */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { normalizeShippingAddress, getExpressCheckoutData } from './';

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
			}
		} );
		document
			.querySelector(
				'form.woocommerce-shipping-calculator [name="calc_shipping"]'
			)
			?.click();
	} else if ( context === 'checkout' ) {
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
			}
		} );
	}
};
