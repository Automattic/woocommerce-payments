/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Transforms the data from the Store API Cart response to `displayItems` for the Stripe PRB.
 * See https://docs.stripe.com/js/appendix/payment_item_object for the data structure
 *
 * @param {Object} cartData Store API Cart response object.
 * @return {{pending: boolean, label: string, amount: integer}} `displayItems` for Stripe.
 */
export const transformCartDataForDisplayItems = ( cartData ) => {
	const displayItems = cartData.items.map( ( item ) => ( {
		amount: parseInt( item.prices.price, 10 ),
		// TODO: should we also add variation attributes?
		label: [ item.name, item.quantity > 1 && ` (x${ item.quantity })` ]
			.filter( Boolean )
			.join( '' ),
		pending: true,
	} ) );

	if ( cartData.totals.total_tax ) {
		displayItems.push( {
			amount: parseInt( cartData.totals.total_tax, 10 ),
			label: __( 'Tax', 'woocommerce-payments' ),
			pending: true,
		} );
	}

	if ( cartData.totals.total_shipping ) {
		displayItems.push( {
			amount: parseInt( cartData.totals.total_shipping, 10 ),
			label: __( 'Shipping', 'woocommerce-payments' ),
			pending: true,
		} );
	}

	return displayItems;
};

/**
 * Transforms the data from the Store API Cart response to `shippingOptions` for the Stripe PRB.
 *
 * @param {Object} cartData Store API Cart response object.
 * @return {{id: string, label: string, amount: integer, detail: string}} `shippingOptions` for Stripe.
 */
export const transformCartDataForShippingOptions = ( cartData ) =>
	cartData.shipping_rates[ 0 ].shipping_rates.map( ( rate ) => ( {
		id: rate.rate_id,
		label: rate.name,
		amount: parseInt( rate.price, 10 ),
		detail: '',
	} ) );
