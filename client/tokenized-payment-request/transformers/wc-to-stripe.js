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
		label: [
			item.name,
			item.quantity > 1 && `(x${ item.quantity })`,
			item.variation &&
				item.variation
					.map(
						( variation ) =>
							`${ variation.attribute }: ${ variation.value }`
					)
					.join( ', ' ),
		]
			.filter( Boolean )
			.join( ' ' ),
	} ) );

	const taxAmount = parseInt( cartData.totals.total_tax || '0', 10 );
	if ( taxAmount ) {
		displayItems.push( {
			amount: taxAmount,
			label: __( 'Tax', 'woocommerce-payments' ),
		} );
	}

	const shippingAmount = parseInt(
		cartData.totals.total_shipping || '0',
		10
	);
	if ( shippingAmount ) {
		displayItems.push( {
			amount: shippingAmount,
			label: __( 'Shipping', 'woocommerce-payments' ),
		} );
	}

	const refundAmount = parseInt( cartData.totals.total_refund || '0', 10 );
	if ( refundAmount ) {
		displayItems.push( {
			amount: -refundAmount,
			label: __( 'Refund', 'woocommerce-payments' ),
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
