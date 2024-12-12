/* global jQuery */

/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

addFilter(
	'wcpay.express-checkout.cart-place-order-extension-data',
	'automattic/wcpay/express-checkout',
	( extensionData ) => {
		const orderAttributionValues = jQuery(
			'#wcpay-express-checkout__order-attribution-inputs input'
		);

		if ( ! orderAttributionValues.length ) {
			return extensionData;
		}

		const orderAttributionData = {};
		orderAttributionValues.each( function () {
			const name = jQuery( this )
				.attr( 'name' )
				.replace( 'wc_order_attribution_', '' );
			const value = jQuery( this ).val();

			if ( name && value ) {
				orderAttributionData[ name ] = value;
			}
		} );

		return {
			...extensionData,
			'woocommerce/order-attribution': orderAttributionData,
		};
	}
);
