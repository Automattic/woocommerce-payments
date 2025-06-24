/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from '../utils';
import { applyFilters } from '@wordpress/hooks';
import { SHIPPING_RATES_UPPER_LIMIT_COUNT } from 'wcpay/express-checkout/constants';

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
		getExpressCheckoutData( 'checkout' )?.currency_decimals ?? 2;

	// making sure the decimals are always correctly represented for GooglePay/ApplePay, since they don't allow us to specify the decimals.
	return price * 10 ** ( currencyDecimals - priceObject.currency_minor_unit );
};

/**
 * Transforms the data from the Store API Cart response to `displayItems` for the Stripe ECE.
 * See for the data structure:
 * - https://docs.stripe.com/js/elements_object/express_checkout_element_shippingaddresschange_event
 * - https://docs.stripe.com/js/elements_object/express_checkout_element_shippingratechange_event
 *
 * @param {Object} rawCartData Store API Cart response object.
 * @return {{pending: boolean, name: string, amount: integer}} `displayItems` for Stripe.
 */
export const transformCartDataForDisplayItems = ( rawCartData ) => {
	const displayPriceIncludingTax = getExpressCheckoutData( 'checkout' )
		.display_prices_with_tax;
	// allowing extensions to manipulate the individual items returned by the backend.
	const cartData = applyFilters(
		'wcpay.express-checkout.map-line-items',
		rawCartData
	);

	const displayItems = cartData.items.map( ( item ) => ( {
		amount: transformPrice(
			displayPriceIncludingTax && item.totals
				? parseInt( item.totals.line_subtotal, 10 ) +
						parseInt( item.totals.line_subtotal_tax, 10 )
				: parseInt(
						item.totals?.line_subtotal || item.prices.price,
						10
				  ),
			item.totals || item.prices
		),
		name: [
			item.name,
			item.quantity > 1 && `(x${ item.quantity })`,
			item.variation && item.variation.length > 0 && '-',
			item.variation &&
				item.variation
					.map(
						( variation ) =>
							`${ variation.attribute }: ${ variation.value }`
					)
					.join( ', ' ),
			item.item_data && item.item_data.length > 0 && '-',
			item.item_data &&
				item.item_data
					.map(
						( itemData ) =>
							`${ itemData.name || itemData.key }: ${
								itemData.value
							}`
					)
					.join( ', ' ),
		]
			.filter( Boolean )
			.map( decodeEntities )
			.join( ' ' ),
	} ) );

	const shippingAmount = parseInt(
		cartData.totals.total_shipping || '0',
		10
	);
	if ( shippingAmount ) {
		displayItems.push( {
			amount: transformPrice(
				displayPriceIncludingTax
					? shippingAmount +
							parseInt(
								cartData.totals.total_shipping_tax || '0',
								10
							)
					: shippingAmount,
				cartData.totals
			),
			name: __( 'Shipping', 'woocommerce-payments' ),
		} );
	}

	const discountsAmount = parseInt(
		cartData.totals.total_discount || '0',
		10
	);
	if ( discountsAmount ) {
		displayItems.push( {
			amount: -transformPrice(
				displayPriceIncludingTax
					? discountsAmount +
							parseInt(
								cartData.totals.total_discount_tax || '0',
								10
							)
					: discountsAmount,
				cartData.totals
			),
			name: __( 'Discount', 'woocommerce-payments' ),
		} );
	}

	const feesAmount = parseInt( cartData.totals.total_fees || '0', 10 );
	if ( feesAmount ) {
		displayItems.push( {
			amount: transformPrice(
				displayPriceIncludingTax
					? feesAmount +
							parseInt(
								cartData.totals.total_fees_tax || '0',
								10
							)
					: feesAmount,
				cartData.totals
			),
			name: __( 'Fees', 'woocommerce-payments' ),
		} );
	}

	const taxAmount = parseInt( cartData.totals.total_tax || '0', 10 );
	if ( taxAmount && ! displayPriceIncludingTax ) {
		displayItems.push( {
			amount: transformPrice( taxAmount, cartData.totals ),
			name: __( 'Tax', 'woocommerce-payments' ),
		} );
	}

	const refundAmount = parseInt( cartData.totals.total_refund || '0', 10 );
	if ( refundAmount ) {
		displayItems.push( {
			amount: -transformPrice( refundAmount, cartData.totals ),
			name: __( 'Refund', 'woocommerce-payments' ),
		} );
	}

	const totalAmount = transformPrice(
		parseInt( cartData.totals.total_price, 10 ) -
			parseInt( cartData.totals.total_refund || 0, 10 ),
		cartData.totals
	);
	const totalAmountOfDisplayItems = displayItems.reduce(
		( acc, { amount } ) => acc + amount,
		0
	);

	// if `totalAmount` is less than the total of `displayItems`, Stripe throws an error
	// it can sometimes happen that the total is _slightly_ less, due to rounding errors on individual items/taxes/shipping
	// (or with the `woocommerce_tax_round_at_subtotal` setting).
	// if that happens, let's just not return any of the line items. This way, just the total amount will be displayed to the customer.
	if ( totalAmount < totalAmountOfDisplayItems ) {
		return [];
	}

	return displayItems;
};

/**
 * Transforms the data from the Store API Cart response to `shippingRates` for the Stripe ECE.
 *
 * @param {Object} cartData Store API Cart response object.
 * @return {{id: string, label: string, amount: integer, deliveryEstimate: string}} `shippingRates` for Stripe.
 */
export const transformCartDataForShippingRates = ( cartData ) => {
	const displayPriceIncludingTax = getExpressCheckoutData( 'checkout' )
		.display_prices_with_tax;

	return cartData.shipping_rates?.[ 0 ]?.shipping_rates
		.sort( ( rateA, rateB ) => {
			if ( rateA.selected === rateB.selected ) {
				return 0; // Keep relative order if both have the same value for 'selected'
			}

			return rateA.selected ? -1 : 1; // Objects with 'selected: true' come first
		} )
		.slice( 0, SHIPPING_RATES_UPPER_LIMIT_COUNT )
		.map( ( rate ) => ( {
			id: rate.rate_id,
			displayName: decodeEntities( rate.name ),
			amount: transformPrice(
				displayPriceIncludingTax
					? parseInt( rate.price, 10 ) + parseInt( rate.taxes, 10 )
					: parseInt( rate.price, 10 ),
				rate
			),
			deliveryEstimate: [
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
};
