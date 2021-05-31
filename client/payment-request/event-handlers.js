/**
 * Internal dependencies
 */
import {
	normalizeShippingAddress,
	normalizeOrderData,
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

	if ( 'success' === response.result ) {
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
	} else {
		abortPayment( event, getErrorMessageFromNotice( response.messages ) );
	}
};
