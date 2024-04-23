import { __ } from '@wordpress/i18n';

export const transformCartDataForDisplayItems = ( cartData ) => {
	// see https://docs.stripe.com/js/appendix/payment_item_object for the data structure
	const displayItems = cartData.items.map( ( item ) => ( {
		amount: item.prices.price,
		// TODO: should we also add variation attributes?
		label: [ item.name, item.quantity > 1 && ` (x${ item.quantity })` ]
			.filter( Boolean )
			.join( '' ),
		pending: true,
	} ) );

	if ( cartData.totals.total_tax ) {
		displayItems.push( {
			amount: cartData.totals.total_tax,
			label: __( 'Tax', 'woocommerce-payments' ),
			pending: true,
		} );
	}

	if ( displayItems.totals.total_shipping ) {
		displayItems.push( {
			amount: cartData.totals.total_shipping,
			label: __( 'Shipping', 'woocommerce-payments' ),
			pending: true,
		} );
	}

	return displayItems;
};
