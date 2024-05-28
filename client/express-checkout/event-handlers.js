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
	event.resolve( {
		shippingRates: response.shipping_options,
	} );
};

const paymentResponseHandler = async (
	api,
	createOrderResponse,
	completePayment,
	abortPayment,
	event
) => {
	console.log( 'paymentResponseHandler' );
	if ( createOrderResponse.result !== 'success' ) {
		return abortPayment(
			event,
			getErrorMessageFromNotice( createOrderResponse.messages )
		);
	}

	try {
		const confirmationRequest = api.confirmIntent(
			createOrderResponse.redirect
		);
		// We need to call `complete` outside of `completePayment` to close the dialog for 3DS.
		// event.complete( 'success' );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( createOrderResponse.redirect );
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
	stripe,
	elements,
	completePayment,
	abortPayment,
	event
) => {
	console.log( 'onConfirmHandler', event );

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );

	if (error) {
		abortPayment( event, error.message );
		return;
	}

	// Kick off checkout processing step.
	const createOrderResponse = await api.expressCheckoutECECreateOrder(
		normalizeOrderData( event, paymentMethod.id )
	);

	console.log( 'createOrderResponse', createOrderResponse );

	paymentResponseHandler(
		api,
		createOrderResponse,
		completePayment,
		abortPayment,
		event
	);
};
