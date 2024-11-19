/* global jQuery */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import {
	getErrorMessageFromNotice,
	normalizeOrderData,
	normalizePayForOrderData,
	getExpressCheckoutData,
} from './utils';
import {
	trackExpressCheckoutButtonClick,
	trackExpressCheckoutButtonLoad,
} from './tracking';
import ExpressCheckoutCartApi from './cart-api';
import { transformStripeShippingAddressForStoreApi } from './transformers/stripe-to-wc';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingOptions,
	transformPrice,
} from './transformers/wc-to-stripe';

let cartApi = new ExpressCheckoutCartApi();
export const setCartApiHandler = ( handler ) => ( cartApi = handler );
export const getCartApiHandler = () => cartApi;

export const shippingAddressChangeHandler = async ( api, event, elements ) => {
	try {
		// Please note that the `event.address` might not contain all the fields.
		// Some fields might not be present (like `line_1` or `line_2`) due to semi-anonymized data.
		const cartData = await cartApi.updateCustomer( {
			shipping_address: transformStripeShippingAddressForStoreApi(
				event.name,
				event.address
			),
		} );

		const shippingOptions = transformCartDataForShippingOptions( cartData );

		// when no shipping options are returned, the API still returns a 200 status code.
		// We need to ensure that shipping options are present - otherwise the PRB dialog won't update correctly.
		if ( shippingOptions.length === 0 ) {
			event.reject();

			return;
		}

		elements.update( {
			// TODO ~FR: fix price transformation for Japanese Yen.
			amount: transformPrice(
				parseInt( cartData.totals.total_price, 10 ) -
					parseInt( cartData.totals.total_refund || 0, 10 ),
				cartData.totals
			),
		} );
		event.resolve( {
			shippingRates: transformCartDataForShippingOptions( cartData ),
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( error ) {
		event.reject();
	}
};

export const shippingRateChangeHandler = async ( api, event, elements ) => {
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

// TODO ~FR note: used by both classic & shortcode-based checkout
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

	try {
		// Kick off checkout processing step.
		let orderResponse;
		if ( ! order ) {
			// TODO ~FR: replace with cartApi
			orderResponse = await api.expressCheckoutECECreateOrder(
				normalizeOrderData( event, paymentMethod.id )
			);
		} else {
			// TODO ~FR: replace with cartApi
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

		const confirmationRequest = api.confirmIntent( orderResponse.redirect );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( orderResponse.redirect );
		} else {
			const redirectUrl = await confirmationRequest;

			completePayment( redirectUrl );
		}
	} catch ( e ) {
		return abortPayment(
			event,
			e.message ??
				__(
					'There was a problem processing the order.',
					'woocommerce-payments'
				)
		);
	}
};

// TODO ~FR note: used by both classic & shortcode-based checkout
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

// TODO ~FR note: used by both classic & shortcode-based checkout
export const onClickHandler = async function ( { expressPaymentType } ) {
	blockUI();
	trackExpressCheckoutButtonClick(
		expressPaymentType,
		getExpressCheckoutData( 'button_context' )
	);
};

// TODO ~FR note: used by both classic & shortcode-based checkout
export const onAbortPaymentHandler = () => {
	unblockUI();
};

// TODO ~FR note: used by both classic & shortcode-based checkout
export const onCompletePaymentHandler = () => {
	blockUI();
};

// TODO ~FR note: used by both classic & shortcode-based checkout
export const onCancelHandler = () => {
	unblockUI();
};
