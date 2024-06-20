/**
 * Internal dependencies
 */
import {
	normalizeOrderData,
	normalizeShippingAddress,
	normalizeLineItems,
} from './utils';
import { getErrorMessageFromNotice } from './utils/index';

export const shippingAddressChangeHandler = async ( api, event, elements ) => {
	try {
		const response = await api.expressCheckoutECECalculateShippingOptions(
			normalizeShippingAddress( event.address )
		);

		if ( response.result === 'success' ) {
			elements.update( {
				amount: response.total.amount,
			} );
			event.resolve( {
				shippingRates: response.shipping_options,
				lineItems: normalizeLineItems( response.displayItems ),
			} );
		} else {
			event.reject();
		}
	} catch ( e ) {
		event.reject();
	}
};

export const shippingRateChangeHandler = async ( api, event, elements ) => {
	try {
		const response = await api.paymentRequestUpdateShippingDetails(
			event.shippingRate
		);

		if ( response.result === 'success' ) {
			elements.update( { amount: response.total.amount } );
			event.resolve( {
				lineItems: normalizeLineItems( response.displayItems ),
			} );
		} else {
			event.reject();
		}
	} catch ( e ) {
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
	const { error: submitError } = await elements.submit();
	if ( submitError ) {
		return abortPayment( event, submitError.message );
	}

	const { paymentMethod, error } = await stripe.createPaymentMethod( {
		elements,
	} );

	if ( error ) {
		return abortPayment( event, error.message );
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
		return abortPayment( event, e.message );
	}
};
