/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { getPaymentRequestData } from '../frontend-utils';

/**
 * GooglePay/ApplePay expect the prices to be formatted in cents.
 * But WooCommerce has a setting to define the number of decimals for amounts.
 * Using this function to ensure the prices provided to GooglePay/ApplePay
 * are always provided accurately, regardless of the number of decimals.
 *
 * @param {number} price the price to format.
 * @param {{currency_minor_unit: {number}}} priceObject the price object returned by the Store API
 *
 * @return {number} the price amount for GooglePay/ApplePay, always expressed in cents.
 */
export const transformPrice = ( price, priceObject ) => {
	const currencyDecimals =
		getPaymentRequestData( 'checkout' )?.currency_decimals ?? 2;

	// making sure the decimals are always correctly represented for GooglePay/ApplePay, since they don't allow us to specify the decimals.
	return price * 10 ** ( currencyDecimals - priceObject.currency_minor_unit );
};

/**
 * Transforms the data from the Store API Cart response to `displayItems` for the Stripe PRB.
 * See https://docs.stripe.com/js/appendix/payment_item_object for the data structure
 *
 * @param {Object} cartData Store API Cart response object.
 * @return {{pending: boolean, label: string, amount: integer}} `displayItems` for Stripe.
 */
export const transformCartDataForDisplayItems = ( cartData ) => {
	const displayItems = cartData.items.map( ( item ) => ( {
		amount: transformPrice(
			parseInt( item.prices.price, 10 ),
			item.prices
		),
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
			amount: transformPrice( taxAmount, cartData.totals ),
			label: __( 'Tax', 'woocommerce-payments' ),
		} );
	}

	const shippingAmount = parseInt(
		cartData.totals.total_shipping || '0',
		10
	);
	if ( shippingAmount ) {
		displayItems.push( {
			amount: transformPrice( shippingAmount, cartData.totals ),
			label: __( 'Shipping', 'woocommerce-payments' ),
		} );
	}

	const refundAmount = parseInt( cartData.totals.total_refund || '0', 10 );
	if ( refundAmount ) {
		displayItems.push( {
			amount: -transformPrice( refundAmount, cartData.totals ),
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
		label: decodeEntities( rate.name ),
		amount: transformPrice( parseInt( rate.price, 10 ), rate ),
		detail: [
			rate.meta_data.find(
				( metadata ) => metadata.key === 'pickup_address'
			)?.value,
			rate.meta_data.find(
				( metadata ) => metadata.key === 'pickup_details'
			)?.value,
		]
			.filter( Boolean )
			.map( decodeEntities )
			.join( ' - ' ),
	} ) );
