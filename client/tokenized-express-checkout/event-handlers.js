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
	normalizeShippingAddress,
	normalizeLineItems,
	getExpressCheckoutData,
} from './utils';
import {
	trackExpressCheckoutButtonClick,
	trackExpressCheckoutButtonLoad,
} from './tracking';
import ExpressCheckoutCartApi from './cart-api';

let cartApi = new ExpressCheckoutCartApi();
export const setCartApiHandler = ( handler ) => ( cartApi = handler );
export const getCartApiHandler = () => cartApi;

// TODO ~FR note: used by both classic & shortcode-based checkout
export const shippingAddressChangeHandler = async ( api, event, elements ) => {
	try {
		// TODO ~FR: replace with cartApi
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

// TODO ~FR note: used by both classic & shortcode-based checkout
export const shippingRateChangeHandler = async ( api, event, elements ) => {
	try {
		// TODO ~FR: replace with cartApi
		const response = await api.expressCheckoutECEUpdateShippingDetails(
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
