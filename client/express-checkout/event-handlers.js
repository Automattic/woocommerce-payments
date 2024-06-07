/**
 * Internal dependencies
 */
import { normalizeOrderData, normalizeShippingAddress } from './utils';
import { getErrorMessageFromNotice } from 'utils/express-checkout';

export const shippingAddressChangeHandler = async ( api, event, elements ) => {
	const response = await api.expressCheckoutECECalculateShippingOptions(
		normalizeShippingAddress( event.address )
	);

	if ( response.result === 'success' ) {
		elements.update( {
			amount: response.total.amount,
		} );
		event.resolve( {
			shippingRates: response.shipping_options,
		} );
	} else {
		event.reject();
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
	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );

	if ( error ) {
		abortPayment( event, error.message );
		return;
	}

	// Kick off checkout processing step.
	const createOrderResponse = await api.expressCheckoutECECreateOrder(
		normalizeOrderData( event, paymentMethod.id )
	);

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

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( createOrderResponse.redirect );
		} else {
			const redirectUrl = await confirmationRequest;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		abortPayment( event, error.message );
	}
};
