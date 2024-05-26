/**
 * Internal dependencies
 */
import { normalizeOrderData, normalizeShippingAddress } from './utils';
import { getErrorMessageFromNotice } from 'express-checkout/utils';

export const shippingAddressChangeHandler = async ( api, event ) => {
	console.log( 'shippingAddressChangeHandler', event );
	const response = await api.paymentRequestCalculateShippingOptions(
		normalizeShippingAddress( event.shippingAddress )
	);
	console.log( response );

	event.resolve( {
		shippingRates: response.shipping_options,
	} );
};

const paymentResponseHandler = async (
	api,
	response,
	completePayment,
	abortPayment,
	event
) => {
	return;
	if ( response.result !== 'success' ) {
		return abortPayment(
			event,
			getErrorMessageFromNotice( response.messages )
		);
	}

	try {
		const confirmationRequest = api.confirmIntent( response.redirect );
		// We need to call `complete` outside of `completePayment` to close the dialog for 3DS.
		event.complete( 'success' );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( response.redirect );
		} else {
			const redirectUrl = await confirmationRequest;

			completePayment( redirectUrl );
		}
	} catch ( error ) {
		abortPayment( event, error.message );
	}
};

export const onConfirmHandler = async (
	api,
	completePayment,
	abortPayment,
	event
) => {
	console.log( api );
	console.log( event );
	// Kick off checkout processing step.
	const response = await api.expressCheckoutECECreateOrder(
		normalizeOrderData( event )
	);

	console.log( response );

	paymentResponseHandler(
		api,
		response,
		completePayment,
		abortPayment,
		event
	);
};
