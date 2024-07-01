/**
 * Internal dependencies
 */
import {
	normalizeOrderData,
	normalizePayForOrderData,
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
	event,
	order = 0 // Order ID for the pay for order flow.
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
	let orderResponse;
	if ( ! order ) {
		orderResponse = await api.expressCheckoutECECreateOrder(
			normalizeOrderData( event, paymentMethod.id )
		);
	} else {
		orderResponse = await api.expressCheckoutECEPayForOrder(
			order,
			normalizePayForOrderData( event, paymentMethod.id )
		);
	}

	if ( orderResponse.result !== 'success' ) {
		return abortPayment(
			event,
			getErrorMessageFromNotice( orderResponse.messages )
		);
	}

	try {
		const confirmationRequest = api.confirmIntent( orderResponse.redirect );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( orderResponse.redirect );
		} else {
			const redirectUrl = await confirmationRequest;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		return abortPayment( event, e.message );
	}
};
