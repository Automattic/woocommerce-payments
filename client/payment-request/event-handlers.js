/**
 * Internal dependencies
 */
import {
	normalizeShippingAddress,
	normalizeOrderData,
	normalizePayForOrderData,
	getErrorMessageFromNotice,
} from './utils';

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
	const response = await api.paymentRequestUpdateShippingDetails(
		event.shippingOption
	);

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

const paymentResponseHandler = async (
	api,
	response,
	completePayment,
	abortPayment,
	event
) => {
	if ( 'success' !== response.result ) {
		abortPayment( event, getErrorMessageFromNotice( response.messages ) );
	}

	try {
		const confirmation = api.confirmIntent( response.redirect );
		// We need to call `complete` outside of `completePayment` to close the dialog for 3DS.
		event.complete( 'success' );

		// `true` means there is no intent to confirm.
		if ( true === confirmation ) {
			completePayment( response.redirect );
		} else {
			const { request } = confirmation;
			const redirectUrl = await request;

			completePayment( redirectUrl );
		}
	} catch ( error ) {
		abortPayment( event, error.message );
	}
};

export const paymentMethodHandler = async (
	api,
	completePayment,
	abortPayment,
	event
) => {
	// Kick off checkout processing step.
	const response = await api.paymentRequestCreateOrder(
		normalizeOrderData( event )
	);

	paymentResponseHandler(
		api,
		response,
		completePayment,
		abortPayment,
		event
	);
};

/**
 * Generates a pay for order handler based on a particular order.
 *
 * @param {integer} order The ID of the order that is being paid.
 * @return {Function} The handler.
 */
export const payForOrderHandler = ( order ) =>
	/**
	 * Same as `paymentMethodHandler`, but for the Pay for Order page.
	 *
	 * @param {WCPayAPI} api The API class.
	 * @param {Function} completePayment A callback for successful payments.
	 * @param {Function} abortPayment A callback for errors.
	 * @param {Object} event The event data, as provided by the Stripe API.
	 */
	async ( api, completePayment, abortPayment, event ) => {
		const response = await api.paymentRequestPayForOrder(
			order,
			normalizePayForOrderData( event )
		);

		paymentResponseHandler(
			api,
			response,
			completePayment,
			abortPayment,
			event
		);
	};
