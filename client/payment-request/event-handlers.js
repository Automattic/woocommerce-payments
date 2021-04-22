/**
 * Internal dependencies
 */
import {
	paymentRequestCalculateShippingOptions,
	paymentRequestUpdateShippingDetails,
	paymentRequestCreateOrder,
} from './api';
import {
	getPaymentRequestData,
	normalizeShippingAddress,
	normalizeOrderData,
} from './utils';

export const shippingAddressChangeHandler = async ( event ) => {
	const response = await paymentRequestCalculateShippingOptions(
		normalizeShippingAddress( event.shippingAddress )
	);

	// Possible statuses success, fail, invalid_payer_name, invalid_payer_email, invalid_payer_phone, invalid_shipping_address.
	event.updateWith( {
		status: response.result,
		shippingOptions: response.shipping_options,
		total: response.total,
		displayItems: response.displayItems,
	} );
};

export const shippingOptionChangeHandler = async ( event ) => {
	const response = await paymentRequestUpdateShippingDetails( event );

	if ( 'success' === response.result ) {
		event.updateWith( {
			status: 'success',
			total: response.total,
			displayItems: response.displayItems,
		} );
	}

	if ( 'fail' === response.result ) {
		event.updateWith( { status: 'fail' } );
	}
};

export const paymentMethodHandler = async ( event ) => {
	const allowPrepaidCard = getPaymentRequestData( 'stripe' )
		?.allow_prepaid_card;
	if ( ! allowPrepaidCard && 'prepaid' === event.source.card.funding ) {
		event.complete( 'fail' );
		// - TODO: Fix error message
		// setExpressPaymentError(
		// 	__(
		// 		"Sorry, we're not accepting prepaid cards at this time.",
		// 		'woocommerce-gateway-stripe'
		// 	)
		// );
		return;
	}

	// Kick off checkout processing step.
	const response = await paymentRequestCreateOrder(
		normalizeOrderData( event )
	);

	if ( 'success' === response.result ) {
		event.complete( 'success' );
		window.location = response.redirect;
	} else {
		event.complete( 'fail' );
		// - TODO: Fix error message
		// setExpressPaymentError(
		// 	getErrorMessageFromNotice( response.messages )
		// );
	}
};
