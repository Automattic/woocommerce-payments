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
		// TODO ~FR: correct status
		status: 'success',
		// TODO ~FR: it shouldn't be a problem for product pages,
		//  but on cart pages with potentially multiple packages, things might get sketchy.
		shippingOptions: response.shipping_rates[ 0 ].shipping_rates.map(
			( rate ) => ( {
				id: rate.rate_id,
				label: rate.name,
				amount: parseInt( rate.price, 10 ),
				detail: '',
			} )
		),
		total: {
			label: '', // TODO ~FR,
			amount: parseInt( response.totals.total_price, 10 ),
			pending: false,
		},
		displayItems: [
			...response.items.map( ( item ) => ( {
				label: item.name,
				amount: parseInt( item.prices.price, 10 ),
			} ) ),
			{
				// TODO ~FR,
				label: 'Tax',
				amount: parseInt( response.totals.total_tax, 10 ),
			},
			{
				// TODO ~FR,
				label: 'Shipping',
				amount: parseInt( response.totals.total_shipping, 10 ),
			},
		],
	} );
};

export const shippingOptionChangeHandler = async ( api, event ) => {
	// TODO ~FR: send updated info to cart with cart token, and update accordingly.
	const response = await api.paymentRequestUpdateShippingDetails(
		event.shippingOption
	);

	event.updateWith( {
		// TODO ~FR: correct status
		status: 'success',
		total: {
			label: '', // TODO ~FR,
			amount: parseInt( response.totals.total_price, 10 ),
			pending: false,
		},
		displayItems: [
			...response.items.map( ( item ) => ( {
				label: item.name,
				amount: parseInt( item.prices.price, 10 ),
			} ) ),
			{
				// TODO ~FR,
				label: 'Tax',
				amount: parseInt( response.totals.total_tax, 10 ),
			},
			{
				// TODO ~FR,
				label: 'Shipping',
				amount: parseInt( response.totals.total_shipping, 10 ),
			},
		],
	} );
};

const paymentResponseHandler = async (
	api,
	response,
	completePayment,
	abortPayment,
	event
) => {
	if ( response.payment_result.payment_status !== 'success' ) {
		return abortPayment(
			event,
			// TODO ~FR
			getErrorMessageFromNotice( response.messages )
		);
	}

	try {
		const confirmationRequest = api.confirmIntent(
			response.payment_result.redirect_url
		);
		// We need to call `complete` outside of `completePayment` to close the dialog for 3DS.
		event.complete( 'success' );

		// TODO ~FR: handle 3ds
		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( response.payment_result.redirect_url );
		} else {
			const redirectUrl = await confirmationRequest;

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
