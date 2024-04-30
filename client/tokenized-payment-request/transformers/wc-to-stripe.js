/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const transformCartDataForDisplayItems = ( cartData ) => {
	// see https://docs.stripe.com/js/appendix/payment_item_object for the data structure
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

export const transformCartDataForShippingOptions = ( cartData ) =>
	cartData.shipping_rates[ 0 ].shipping_rates.map( ( rate ) => ( {
		id: rate.rate_id,
		label: rate.name,
		amount: parseInt( rate.price, 10 ),
		detail: '',
	} ) );
