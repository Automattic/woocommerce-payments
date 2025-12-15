/* global jQuery */

/**
 * External dependencies
 */
import { addFilter } from '@wordpress/hooks';

const ORDER_ATTRIBUTION_ELEMENT_ID =
	'wcpay-express-checkout__order-attribution-inputs';

/**
 * Add an order attribution inputs element to the page if it doesn't exist.
 * On block-based checkout, the PHP-rendered element may not be present.
 */
const addOrderAttributionInputsIfNotExists = () => {
	// in case it was already added by the PHP renderer.
	if ( document.getElementById( ORDER_ATTRIBUTION_ELEMENT_ID ) ) {
		return;
	}

	// fallback - creating it if it doesn't exist.
	const orderAttributionInputs = document.createElement(
		'wc-order-attribution-inputs'
	);
	orderAttributionInputs.id = ORDER_ATTRIBUTION_ELEMENT_ID;
	document.body.appendChild( orderAttributionInputs );
};

/**
 * Initialize order attribution support.
 * Ensures the element exists and is populated with tracking data.
 */
const init = () => {
	addOrderAttributionInputsIfNotExists();

	// manually calling the helper to ensure the hidden inputs are populated with attribution data.
	window?.wc_order_attribution?.setOrderTracking(
		window?.wc_order_attribution?.params?.allowTracking
	);
};

// Initialize immediately if possible, or wait for DOM ready.
// Just in case `wc_order_attribution` is loaded too late.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}

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
