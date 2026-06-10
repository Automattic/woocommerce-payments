/* global jQuery */
/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	getExpressCheckoutData,
	updateShippingAddressUI,
	shouldUseConfirmationTokens,
} from './utils';
import {
	trackExpressCheckoutButtonClick,
	trackExpressCheckoutButtonLoad,
} from './tracking';
import ExpressCheckoutCartApi from './cart-api';
import { transformStripeShippingAddressForStoreApi } from './transformers/stripe-to-wc';
import {
	transformCartDataForDisplayItems,
	transformCartDataForShippingRates,
	transformPrice,
} from './transformers/wc-to-stripe';
import { getSetupFutureUsageForCart } from './utils/subscriptions';
import { confirmExpressCredential } from './session/express-payment-session';
import { createStoreApiSink } from './session/sinks';

let lastSelectedAddress = null;
let lastCartData = null;
let cartApi = new ExpressCheckoutCartApi();
export const setCartApiHandler = ( handler ) => ( cartApi = handler );
export const getCartApiHandler = () => cartApi;

const getElementsUpdateOptionsForCart = ( cartData ) => ( {
	// Apply filter to allow modifications (e.g., for trial subscriptions with $0 initial payment)
	amount: applyFilters(
		'wcpay.express-checkout.total-amount',
		transformPrice(
			parseInt( cartData.totals.total_price, 10 ) -
				parseInt( cartData.totals.total_refund || 0, 10 ),
			cartData.totals
		),
		cartData
	),
	...( shouldUseConfirmationTokens()
		? { setupFutureUsage: getSetupFutureUsageForCart( cartData ) }
		: {} ),
} );

export const shippingAddressChangeHandler = async ( event, elements ) => {
	lastSelectedAddress = event.address;

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

		await elements.update( getElementsUpdateOptionsForCart( cartData ) );

		lastCartData = cartData;

		event.resolve( {
			shippingRates,
			lineItems: transformCartDataForDisplayItems( cartData ),
		} );
	} catch ( error ) {
		event.reject();
	}
};

export const shippingRateChangeHandler = async (
	event,
	elements,
	currentCartData = null
) => {
	// Use the most recent cart data from a previous address/rate change,
	// falling back to the caller-provided data. This ensures we have
	// up-to-date subscription extension data (e.g., shipping rates for
	// the current address) when resolving the shipping package ID.
	const effectiveCartData = lastCartData || currentCartData;

	try {
		const cartData = await cartApi.selectShippingRate( {
			// Apply filter to get the correct package ID (e.g., for trial subscriptions
			// where shipping is in subscription extensions, not main cart)
			package_id: applyFilters(
				'wcpay.express-checkout.shipping-package-id',
				0,
				effectiveCartData,
				event.shippingRate.id
			),
			rate_id: event.shippingRate.id,
		} );

		lastCartData = cartData;

		await elements.update( getElementsUpdateOptionsForCart( cartData ) );
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
	event,
	paymentMethodTypes = []
) => {
	// The standalone buttons own the address and place the order via the Store
	// API, so they deliver the credential through the Store API sink rather than
	// the checkout form. `cartApi` is read live so test overrides apply.
	const sink = createStoreApiSink( {
		api,
		cartApi,
		event,
		paymentMethodTypes,
		completePayment,
		abortPayment,
	} );

	let credential;
	try {
		credential = await confirmExpressCredential(
			stripe,
			elements,
			shouldUseConfirmationTokens()
		);
	} catch ( e ) {
		return sink.error( e.message );
	}

	return sink.success( credential );
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
