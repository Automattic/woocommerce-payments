/**
 * Internal dependencies
 */
import { normalizeShippingAddress, normalizeOrderData } from './utils';

export const shippingAddressChangeHandler = async ( api, event ) => {
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

export const shippingOptionChangeHandler = async ( api, event ) => {
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

export const paymentMethodHandler = async ( api, event ) => {
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
