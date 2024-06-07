/**
 * Internal dependencies
 */
import {
	normalizeOrderData,
	normalizeShippingAddress,
	normalizeShippingRates,
} from './utils';
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
			shippingRates: normalizeShippingRates( response.shipping_options ),
		} );
	}

	if ( response.result === 'fail' ) {
		event.reject();
	}
};

export const shippingRateChangeHandler = async ( api, event, elements ) => {
	const lightShippingRate = {
		id: event.shippingRate.id,
	};
	const response = await api.paymentRequestUpdateShippingDetails(
		lightShippingRate
	);

	if ( response.result === 'success' ) {
		elements.update( {
			amount: response.total.amount,
		} );

		event.resolve();
	}

	if ( response.result === 'fail' ) {
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
		abortPayment( error.message );
		return;
	}

	// Kick off checkout processing step.
	const createOrderResponse = await api.expressCheckoutECECreateOrder(
		normalizeOrderData( event, paymentMethod.id )
	);

	if ( createOrderResponse.result !== 'success' ) {
		return abortPayment(
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
