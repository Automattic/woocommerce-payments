/**
 * Internal dependencies
 */
import { normalizeShippingAddress, normalizeOrderData } from './utils';

const shippingAddressChangeHandler = async ( api, event ) => {
	const response = await api.paymentRequestCalculateShippingOptions(
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

const shippingOptionChangeHandler = async ( api, event ) => {
	const response = await api.paymentRequestUpdateShippingDetails( event );

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

const paymentMethodHandler = async ( api, event ) => {
	// We retrieve `allowPrepaidCard` like this to ensure we default to false in the
	// event `allowPrepaidCard` isn't present on the server data object.
	// - TODO: Get prepaid card
	// const { allowPrepaidCard = false } = getStripeServerData();
	const allowPrepaidCard = true;
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
	const response = await api.paymentRequestCreateOrder(
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

export {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentMethodHandler,
};
