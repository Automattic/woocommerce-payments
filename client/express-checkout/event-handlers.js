/* global jQuery */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	getErrorMessageFromNotice,
	getExpressCheckoutData,
	updateShippingAddressUI,
	createPaymentCredential,
	shouldUseConfirmationTokens,
} from './utils';
import { getElementCurrency } from './utils/element-currency-cache';
import { recordUserEvent } from 'tracks';
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
import { getSetupFutureUsageForCart } from './utils/subscriptions';

let lastSelectedAddress = null;
let lastCartData = null;
let cartApi = new ExpressCheckoutCartApi();
export const setCartApiHandler = ( handler ) => ( cartApi = handler );
export const getCartApiHandler = () => cartApi;

// An open Apple Pay / Google Pay sheet is locked to the currency it opened
// with. A country-based multi-currency plugin (e.g. Price Based on Country)
// can flip the cart's currency from the address chosen inside the sheet — we
// can't follow that on a live sheet, so the amounts would render in the wrong
// currency and the order would be rejected at placement by the server-side
// currency guard. Detect the drift up front and reject the address instead.
const cartCurrencyDriftedFromElement = ( cartData ) => {
	const elementCurrency = getElementCurrency();
	const cartCurrency = cartData?.totals?.currency_code?.toLowerCase();

	return Boolean(
		elementCurrency && cartCurrency && elementCurrency !== cartCurrency
	);
};

// `event.reject()` only surfaces the wallet's generic "address unsupported"
// message, so callers also pass their context's error surface (the shortcode
// notice or the block's `setExpressPaymentError`) to explain the real reason.
const getCurrencyMismatchMessage = ( cartData ) =>
	sprintf(
		/* translators: 1: currency the express payment started in, 2: currency required by the selected address */
		__(
			'This express payment started in %1$s and cannot switch to %2$s ' +
				'for the address you selected. Choose a different shipping ' +
				'address, or use the regular checkout to pay in %2$s.',
			'woocommerce-payments'
		),
		getElementCurrency().toUpperCase(),
		cartData.totals.currency_code.toUpperCase()
	);

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

// Mirrors the two built-in WooCommerce local-pickup method IDs: `local_pickup`
// (the seed hardcoded in `LocalPickupUtils::get_local_pickup_method_ids()`) and
// `pickup_location` (WC Blocks's PickupLocation method, which dynamically
// appears in that list via `supports('local-pickup')`). Third-party methods
// that opt in via the same `supports('local-pickup')` flag are not covered;
// the two built-in IDs handle the reported customer impact.
const LOCAL_PICKUP_METHOD_IDS = [ 'local_pickup', 'pickup_location' ];

const isLocalPickupRate = ( rate ) =>
	LOCAL_PICKUP_METHOD_IDS.includes( rate?.method_id );

/**
 * When the wallet provides a shipping address the customer clearly wants
 * delivery, but the Store API may still return Local Pickup as the selected
 * rate because WooCommerce keeps the previously chosen pickup method sticky
 * (see `wc_get_default_shipping_method_for_package()`). Reselect the first
 * non-pickup rate so the modal — and the session used at place-order time —
 * align with the customer's intent.
 *
 * Costs one extra Store API round-trip (`select-shipping-rate`) per address
 * change in the bug scenario, because the WC session must be updated server-
 * side. Note this is NOT fully superseded by the WC core origin-tracking fix
 * (WOOPLUG-6671 / woocommerce/woocommerce#64795): core only re-evaluates the
 * chosen method when the package's rate set changes, so when pickup and a
 * delivery rate are both available before the address arrives, this reselect
 * is the only thing that moves the selection off pickup. Keep it until core
 * also re-evaluates auto-defaulted choices on unchanged rate sets.
 *
 * @param {Object} cartData Cart response from `updateCustomer`.
 * @return {Promise<Object>} Cart data with a non-pickup rate selected when applicable.
 */
const preferDeliveryOverLocalPickup = async ( cartData ) => {
	// Resolve the effective rates the same way the display path does — for
	// trial subscriptions with deferred shipping the rates live in the
	// subscription extensions rather than the main cart package.
	const rates =
		applyFilters(
			'wcpay.express-checkout.shipping-rates',
			cartData.shipping_rates?.[ 0 ]?.shipping_rates || [],
			cartData
		) || [];
	const selectedRate = rates.find( ( rate ) => rate.selected );

	if ( ! selectedRate || ! isLocalPickupRate( selectedRate ) ) {
		return cartData;
	}

	const firstDeliveryRate = rates.find(
		( rate ) => ! isLocalPickupRate( rate )
	);
	if ( ! firstDeliveryRate ) {
		return cartData;
	}

	try {
		return await cartApi.selectShippingRate( {
			package_id: applyFilters(
				'wcpay.express-checkout.shipping-package-id',
				0,
				cartData,
				firstDeliveryRate.rate_id
			),
			rate_id: firstDeliveryRate.rate_id,
		} );
	} catch ( e ) {
		// Fall back to the original cart data; the wallet will still show
		// Local Pickup as selected but the user can change it manually.
		// Tracked so a production regression re-exposing the underlying bug
		// is visible to operators, not just the shopper's browser console.
		recordUserEvent( 'express_checkout_pickup_reselect_failed' );
		// eslint-disable-next-line no-console
		console.warn(
			'[WCPay ECE] preferDeliveryOverLocalPickup failed, falling back to original cart:',
			e
		);
		return cartData;
	}
};

export const shippingAddressChangeHandler = async (
	event,
	elements,
	errorHandler
) => {
	lastSelectedAddress = event.address;

	try {
		// Please note that the `event.address` might not contain all the fields.
		// Some fields might not be present (like `line_1` or `line_2`) due to semi-anonymized data.
		const updatedCustomerData = await cartApi.updateCustomer( {
			shipping_address: transformStripeShippingAddressForStoreApi(
				event.name,
				event.address
			),
		} );

		if ( cartCurrencyDriftedFromElement( updatedCustomerData ) ) {
			errorHandler?.( getCurrencyMismatchMessage( updatedCustomerData ) );
			event.reject();

			return;
		}

		const cartData = await preferDeliveryOverLocalPickup(
			updatedCustomerData
		);

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
	currentCartData = null,
	onError
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

		if ( cartCurrencyDriftedFromElement( cartData ) ) {
			onError?.( getCurrencyMismatchMessage( cartData ) );
			event.reject();

			return;
		}

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
	const { error: submitError } = await elements.submit();
	if ( submitError ) {
		return abortPayment( submitError.message );
	}

	const useConfirmationTokens = shouldUseConfirmationTokens();

	let credentialId;
	try {
		credentialId = await createPaymentCredential(
			stripe,
			elements,
			useConfirmationTokens
		);
	} catch ( credentialError ) {
		return abortPayment( credentialError.message );
	}

	try {
		// Kick off checkout processing step.
		const orderResponse = await cartApi.placeOrder( {
			// adding extension data as a separate action,
			// so that we make it harder for external plugins to modify or intercept checkout data.
			...transformStripePaymentMethodForStoreApi(
				event,
				credentialId,
				useConfirmationTokens,
				paymentMethodTypes
			),
			extensions: applyFilters(
				'wcpay.express-checkout.cart-place-order-extension-data',
				{}
			),
		} );

		if ( orderResponse.payment_result.payment_status !== 'success' ) {
			return abortPayment(
				getErrorMessageFromNotice(
					orderResponse.message ??
						orderResponse.payment_result?.payment_details.find(
							( detail ) => detail.key === 'errorMessage'
						)?.value ??
						''
				)
			);
		}

		// Extract redirect URL from payment_details if redirect_url is empty
		let redirectUrl = orderResponse.payment_result.redirect_url;
		if ( ! redirectUrl ) {
			const redirectDetail =
				orderResponse.payment_result.payment_details?.find(
					( detail ) => detail.key === 'redirect'
				);
			redirectUrl = redirectDetail?.value || '';
		}

		const confirmationRequest = api.confirmIntent( redirectUrl );

		// `true` means there is no intent to confirm.
		if ( confirmationRequest === true ) {
			completePayment( redirectUrl );
		} else {
			const authenticatedRedirectUrl = await confirmationRequest;

			completePayment( authenticatedRedirectUrl );
		}
	} catch ( e ) {
		// API errors are not parsed, so we need to do it ourselves.
		if ( e.json ) {
			e = await Promise.resolve( e.json() );
		}

		return abortPayment(
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
