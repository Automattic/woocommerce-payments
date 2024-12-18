/* global jQuery */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	getErrorMessageFromNotice,
	getExpressCheckoutData,
	updateShippingAddressUI,
} from './utils';
import {
	trackExpressCheckoutButtonClick,
	trackExpressCheckoutButtonLoad,
} from './tracking';
import ExpressCheckoutCartApi from './cart-api';
import {
	transformStripePaymentMethodForStoreApi,
	transformStripeShippingAddressForStoreApi,
} from './transformers/stripe-to-wc';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
	transformPrice,
} from './transformers/wc-to-stripe';

let lastSelectedAddress = null;
let cartApi = new ExpressCheckoutCartApi();
export const setCartApiHandler = ( handler ) => ( cartApi = handler );
export const getCartApiHandler = () => cartApi;

export const shippingAddressChangeHandler = async ( event, elements ) => {
	try {
		// Please note that the `event.address` might not contain all the fields.
		// Some fields might not be present (like `line_1` or `line_2`) due to semi-anonymized data.
		const cartData = await cartApi.updateCustomer( {
			shipping_address: transformStripeShippingAddressForStoreApi(
				event.name,
				event.address
			),
		} );

		const shippingRates = transformCartDataForShippingRates( cartData );

		// when no shipping options are returned, the API still returns a 200 status code.
		// We need to ensure that shipping options are present - otherwise the ECE dialog won't update correctly.
		if ( shippingRates.length === 0 ) {
			event.reject();

			return;
		}

		elements.update( {
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
		} );

		lastSelectedAddress = event.address;

		event.resolve( {
			shippingRates: transformCartDataForShippingRates( cartData ),
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( error ) {
		event.reject();
	}
};

export const shippingRateChangeHandler = async ( event, elements ) => {
	try {
		const cartData = await cartApi.selectShippingRate( {
			package_id: 0,
			rate_id: event.shippingRate.id,
		} );

		elements.update( {
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
		} );
		event.resolve( {
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( error ) {
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

	try {
		// Kick off checkout processing step.
		const orderResponse = await cartApi.placeOrder( {
			// adding extension data as a separate action,
			// so that we make it harder for external plugins to modify or intercept checkout data.
			...transformStripePaymentMethodForStoreApi(
				event,
				paymentMethod.id
			),
			extensions: applyFilters(
				'wcpay.express-checkout.cart-place-order-extension-data',
				{}
			),
		} );

		if ( orderResponse.payment_result.payment_status !== 'success' ) {
			return abortPayment(
				event,
				getErrorMessageFromNotice(
					orderResponse.message ??
						orderResponse.payment_result?.payment_details.find(
							( detail ) => detail.key === 'errorMessage'
						)?.value ??
						''
				)
			);
		}

		const confirmationRequest = api.confirmIntent(
			orderResponse.payment_result.redirect_url
		);

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( orderResponse.payment_result.redirect_url );
		} else {
			const redirectUrl = await confirmationRequest;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		// API errors are not parsed, so we need to do it ourselves.
		if ( e.json ) {
			e = e.json();
		}

		return abortPayment(
			event,
			getErrorMessageFromNotice(
				e.message ||
					e.payment_result?.payment_details.find(
						( detail ) => detail.key === 'errorMessage'
					)?.value ||
					__(
						'There was a problem processing the order.',
						'woocommerce-payments'
					)
			)
		);
	}
};

export const onReadyHandler = async function ( { availablePaymentMethods } ) {
	if ( availablePaymentMethods ) {
		const enabledMethods = Object.entries( availablePaymentMethods )
			// eslint-disable-next-line no-unused-vars
			.filter( ( [ _, isEnabled ] ) => isEnabled )
			// eslint-disable-next-line no-unused-vars
			.map( ( [ methodName, _ ] ) => methodName );

		trackExpressCheckoutButtonLoad( {
			paymentMethods: enabledMethods,
			source: getExpressCheckoutData( 'button_context' ),
		} );
	}
};

const blockUI = () => {
	jQuery.blockUI( {
		message: null,
		overlayCSS: {
			background: '#fff',
			opacity: 0.6,
		},
	} );
};

const unblockUI = () => {
	jQuery.unblockUI();
};

export const onClickHandler = async function ( { expressPaymentType } ) {
	blockUI();
	trackExpressCheckoutButtonClick(
		expressPaymentType,
		getExpressCheckoutData( 'button_context' )
	);
};

export const onAbortPaymentHandler = () => {
	unblockUI();
};

export const onCompletePaymentHandler = () => {
	blockUI();
};

export const onCancelHandler = () => {
	if ( lastSelectedAddress ) {
		updateShippingAddressUI( lastSelectedAddress );
	}
	lastSelectedAddress = null;
	unblockUI();
};
