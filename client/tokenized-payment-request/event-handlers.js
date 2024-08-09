/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	transformStripePaymentMethodForStoreApi,
	transformStripeShippingAddressForStoreApi,
} from './transformers/stripe-to-wc';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingOptions,
	transformPrice,
} from './transformers/wc-to-stripe';

import {
	getPaymentRequestData,
	getErrorMessageFromNotice,
} from './frontend-utils';

import PaymentRequestCartApi from './cart-api';

const cartApi = new PaymentRequestCartApi();

export const shippingAddressChangeHandler = async ( event ) => {
	try {
		// Please note that the `event.shippingAddress` might not contain all the fields.
		// Some fields might not be present (like `line_1` or `line_2`) due to semi-anonymized data.
		const cartData = await cartApi.updateCustomer(
			transformStripeShippingAddressForStoreApi( event.shippingAddress )
		);

		const shippingOptions = transformCartDataForShippingOptions( cartData );

		// when no shipping options are returned, the API still returns a 200 status code.
		// We need to ensure that shipping options are present - otherwise the PRB dialog won't update correctly.
		if ( shippingOptions.length === 0 ) {
			event.updateWith( {
				// Possible statuses: https://docs.stripe.com/js/appendix/payment_response#payment_response_object-complete
				status: 'invalid_shipping_address',
			} );

			return;
		}

		event.updateWith( {
			// Possible statuses: https://docs.stripe.com/js/appendix/payment_response#payment_response_object-complete
			status: 'success',
			shippingOptions: transformCartDataForShippingOptions( cartData ),
			total: {
				label: getPaymentRequestData( 'total_label' ),
				amount: transformPrice(
					parseInt( cartData.totals.total_price, 10 ) -
						parseInt( cartData.totals.total_refund || 0, 10 ),
					cartData.totals
				),
			},
			displayItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( error ) {
		// Possible statuses: https://docs.stripe.com/js/appendix/payment_response#payment_response_object-complete
		event.updateWith( {
			status: 'fail',
		} );
	}
};

export const shippingOptionChangeHandler = async ( event ) => {
	try {
		const cartData = await cartApi.selectShippingRate( {
			package_id: 0,
			rate_id: event.shippingOption.id,
		} );

		event.updateWith( {
			status: 'success',
			total: {
				label: getPaymentRequestData( 'total_label' ),
				amount: transformPrice(
					parseInt( cartData.totals.total_price, 10 ) -
						parseInt( cartData.totals.total_refund || 0, 10 ),
					cartData.totals
				),
			},
			displayItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( error ) {
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
	if ( response.payment_result.payment_status !== 'success' ) {
		return abortPayment(
			event,
			getErrorMessageFromNotice(
				response.message ||
					response.payment_result?.payment_details.find(
						( detail ) => detail.key === 'errorMessage'
					)?.value
			)
		);
	}

	try {
		const confirmationRequest = api.confirmIntent(
			response.payment_result.redirect_url
		);
		// We need to call `complete` outside of `completePayment` to close the dialog for 3DS.
		event.complete( 'success' );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( response.payment_result.redirect_url );
		} else {
			const redirectUrl = await confirmationRequest;

			completePayment( redirectUrl );
		}
	} catch ( error ) {
		abortPayment(
			event,
			getErrorMessageFromNotice(
				error.message ||
					error.payment_result?.payment_details.find(
						( detail ) => detail.key === 'errorMessage'
					)?.value
			)
		);
	}
};

export const paymentMethodHandler = async (
	api,
	completePayment,
	abortPayment,
	event
) => {
	try {
		// Kick off checkout processing step.
		const response = await cartApi.placeOrder( {
			// adding extension data as a separate action,
			// so that we make it harder for external plugins to modify or intercept checkout data.
			...transformStripePaymentMethodForStoreApi( event ),
			extensions: applyFilters(
				'wcpay.payment-request.cart-place-order-extension-data',
				{}
			),
		} );

		paymentResponseHandler(
			api,
			response,
			completePayment,
			abortPayment,
			event
		);
	} catch ( error ) {
		abortPayment(
			event,
			getErrorMessageFromNotice(
				error.message ||
					error.payment_result?.payment_details.find(
						( detail ) => detail.key === 'errorMessage'
					)?.value
			)
		);
	}
};
