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

/**
 * Transform shipping address information from Stripe's address object to
 * the cart shipping address object shape.
 *
 * @param {Object} shippingAddress Stripe's shipping address item
 *
 * @return {Object} The shipping address in the shape expected by the cart.
 */
export const transformStripeShippingAddressForStoreApi = (
	shippingAddress
) => {
	return {
		shipping_address: {
			first_name:
				shippingAddress.recipient
					?.split( ' ' )
					?.slice( 0, 1 )
					?.join( ' ' ) ?? '',
			last_name:
				shippingAddress.recipient
					?.split( ' ' )
					?.slice( 1 )
					?.join( ' ' ) ?? '',
			company: shippingAddress.organization ?? '',
			address_1: shippingAddress.addressLine?.[ 0 ] ?? '',
			address_2: shippingAddress.addressLine?.[ 1 ] ?? '',
			city: shippingAddress.city ?? '',
			state: shippingAddress.region ?? '',
			country: shippingAddress.country ?? '',
			postcode: shippingAddress.postalCode?.replace( ' ', '' ) ?? '',
		},
	};
};

/**
 * Transform order data from Stripe's object to the expected format for WC.
 *
 * @param {Object} paymentData Stripe's order object.
 *
 * @return {Object} Order object in the format WooCommerce expects.
 */
export const transformStripePaymentMethodForStoreApi = ( paymentData ) => {
	const name =
		( paymentData.paymentMethod?.billing_details?.name ??
			paymentData.payerName ) ||
		'';
	const billing = paymentData.paymentMethod?.billing_details?.address ?? {};
	const shipping = paymentData.shippingAddress ?? {};

	let paymentRequestType = 'payment_request_api';
	if ( paymentData.walletName === 'applePay' ) {
		paymentRequestType = 'apple_pay';
	} else if ( paymentData.walletName === 'googlePay' ) {
		paymentRequestType = 'google_pay';
	}

	return {
		customer_note: paymentData.order_comments,
		billing_address: {
			first_name: name.split( ' ' )?.slice( 0, 1 )?.join( ' ' ) ?? '',
			last_name: name.split( ' ' )?.slice( 1 )?.join( ' ' ) || '-',
			company: billing.organization ?? '',
			address_1: billing.line1 ?? '',
			address_2: billing.line2 ?? '',
			city: billing.city ?? '',
			state: billing.state ?? '',
			postcode: billing.postal_code ?? '',
			country: billing.country ?? '',
			email:
				paymentData.paymentMethod?.billing_details?.email ??
				paymentData.payerEmail ??
				'',
			phone:
				paymentData.paymentMethod?.billing_details?.phone ??
				paymentData.payerPhone?.replace( '/[() -]/g', '' ) ??
				'',
		},
		// refreshing any shipping address data, now that the customer is placing the order.
		...transformStripeShippingAddressForStoreApi( shipping ),
		payment_method: 'woocommerce_payments',
		payment_data: [
			{
				key: 'payment_request_type',
				value: paymentRequestType,
			},
			{
				key: 'wcpay-fraud-prevention-token',
				value: window.wcpayFraudPreventionToken ?? '',
			},
			{
				key: 'wcpay-payment-method',
				value: paymentData.paymentMethod?.id,
			},
		],
	};

	// TODO: we also have all this data - do we need to submit it?
	return {
		shipping_method: [ paymentData.shippingOption?.id ?? null ],
		ship_to_different_address: 1,
		terms: 1,
	};
};
