/* eslint-disable prettier/prettier */
/* global jQuery */

/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type {
	PaymentMethodSelectProps,
	WCPayPMSelectorData,
	FetchUserTokensResponse,
} from './types';
import UserTokenCache from './user-token-cache';

/**
 * Add a listener to the customer select.
 *
 * This could be a shorter method, but because the customer select
 * element uses select2, it does not emit the typical `change` event.
 *
 * @param {(userId: number) => void} callback The callback to call when the customer is changed.
 * @return {() => void} The cleanup function.
 */
const addCustomerSelectListener = (
	callback: ( userId: number ) => void
): ( () => void ) => {
	const element = document.getElementById( 'customer_user' );
	const customerUserSelect =
		element instanceof HTMLSelectElement ? element : null;

	if ( ! customerUserSelect ) {
		return (): void => {
			// No-op cleanup function when an element is not found.
		};
	}

	// Wrap in an internal callback to load the select's value.
	const internalCallback = () =>
		callback( parseInt( customerUserSelect.value, 10 ) || 0 );

	// Add the listener with the right technique, as select2 does not emit <select> events.
	jQuery( customerUserSelect ).on( 'select2:select', internalCallback );
	customerUserSelect.addEventListener( 'change', internalCallback );

	return () => {
		jQuery( customerUserSelect ).off( 'select2:select', internalCallback );
		customerUserSelect.removeEventListener( 'change', internalCallback );
	};
};

/**
 * Fetch the tokens for a user from the back-end.
 *
 * @param {number} userId The user ID.
 * @param {string} ajaxUrl The AJAX URL.
 * @param {string} nonce The nonce.
 * @return {Promise<FetchUserTokensResponse | undefined>} The tokens for the user.
 */
const fetchUserTokens = async (
	userId: number,
	ajaxUrl: string,
	nonce: string
): Promise< FetchUserTokensResponse | undefined > => {
	const formData = new FormData();
	formData.append( 'action', 'wcpay_get_user_payment_tokens' );
	formData.append( 'nonce', nonce );
	formData.append( 'user_id', userId.toString() );

	const response = await fetch( ajaxUrl, {
		method: 'POST',
		body: formData,
	} );
	if ( ! response.ok ) {
		throw new Error(
			__( 'Failed to fetch user tokens', 'woocommerce-payments' )
		);
	}

	const result = await response.json();
	return result.data as FetchUserTokensResponse;
};

/**
 * Renders a payment method select or loading indicator.
 *
 * @param {PaymentMethodSelectProps} props The props for the payment method select.
 * @param {string} props.inputName The name attribute for the select element.
 * @param {number} props.value The currently selected payment method token ID.
 * @param {(value: number) => void} props.onChange Callback when the selected value changes.
 * @param {number} props.userId The ID of the customer whose payment methods to display.
 * @param {UserTokenCache} props.cache The cache containing user payment token data.
 * @return {JSX.Element} The payment method select or loading indicator.
 */
export const PaymentMethodSelect = ( {
	inputName,
	value,
	onChange,
	userId,
	cache,
}: PaymentMethodSelectProps ) => {
	/**
	 * Generate options for the select.
	 */
	const options: JSX.Element[] = [];
	if ( userId > 0 ) {
		const entry = cache.getUserEntry( userId );
		if ( undefined === entry || entry.loading ) {
			return <>{ __( 'Loading…', 'woocommerce-payments' ) }</>;
		} else if ( entry.loadingError ) {
			return <strong>{ entry.loadingError }</strong>;
		}

		if ( ! cache.userHasToken( userId, value ) ) {
			options.push(
				<option value={ 0 } key={ 'select' } disabled>
					{ __(
						'Please select a payment method',
						'woocommerce-payments'
					) }
				</option>
			);
		}

		entry.tokens.forEach( ( token ) => {
			options.push(
				<option value={ token.tokenId } key={ token.tokenId }>
					{ token.displayName }
				</option>
			);
		} );
	} else {
		options.push(
			<option value={ 0 } key={ 'no-customer' } disabled>
				{ __(
					'Please select a customer first',
					'woocommerce-payments'
				) }
			</option>
		);
	}

	return (
		// eslint-disable-next-line
		<select
			name={ inputName }
			value={ value }
			onChange={ ( event ) =>
				onChange( parseInt( event.target.value, 10 ) )
			}
		>
			{ options }
		</select>
	);
};

/**
 * Setup the payment method select for a given element.
 *
 * @param {HTMLSpanElement} element The <span> where the payment method select should be rendered.
 * @param {UserTokenCache} cache The cache of user tokens.
 * @return {void}
 */
const setupPaymentSelector = (
	element: HTMLSpanElement,
	cache: UserTokenCache
): void => {
	const data = JSON.parse(
		element.getAttribute( 'data-wcpay-pm-selector' ) || '{}'
	) as WCPayPMSelectorData;

	// Use the values from the data instead of input to ensure correct types.
	let userId = data.userId ?? 0;
	let value = data.value ?? 0;

	// Initial population.
	if ( userId ) {
		cache.add( userId, data.tokens ?? [] );
	}

	// In older Subscriptions versions, there was just a simple input.
	const input = element.querySelector( 'select,input' );
	if ( ! input ) {
		return;
	}
	if (
		! (
			input instanceof HTMLSelectElement ||
			input instanceof HTMLInputElement
		)
	) {
		return;
	}

	const root = createRoot( element );
	const render = () => {
		// If there is no value, but user tokens are loaded and there is a token, use it.
		if ( ! value ) {
			const entry = cache.getUserEntry( userId );
			const defaultToken = entry?.tokens.find(
				( token ) => token.isDefault
			);
			if ( entry && entry.tokens.length > 0 && defaultToken ) {
				value = defaultToken.tokenId;
			}
		}

		root.render(
			<PaymentMethodSelect
				inputName={ input.name }
				value={ value }
				userId={ userId }
				cache={ cache }
				onChange={ ( newValue: number ) => {
					value = newValue;
					render();
				} }
			/>
		);
	};

	render();
	cache.subscribe( render );
	addCustomerSelectListener( async ( newUserId ) => {
		// Once the customer is changed, the selected payment method is lost.
		value = 0;
		userId = newUserId;
		render();

		// Loaded, loading, or errored out, we do not need to load anything.
		if ( cache.hasEntry( userId ) ) {
			return;
		}

		cache.startLoading( userId );

		try {
			const response = await fetchUserTokens(
				userId,
				data.ajaxUrl,
				data.nonce
			);
			if ( undefined === response ) {
				throw new Error(
					__(
						'Failed to fetch user tokens. Please reload the page and try again.',
						'woocommerce-payments'
					)
				);
			}
			cache.tokensLoaded( userId, response.tokens );
		} catch ( error ) {
			cache.loadingFailed(
				userId,
				error instanceof Error
					? error.message
					: __( 'Unknown error', 'woocommerce-payments' )
			);
		}
	} );
};

/**
 * Initializes all payment method dropdown elements on the page.
 *
 * Creates a shared cache for user tokens and sets up payment method
 * selectors for all elements with the .wcpay-subscription-payment-method class.
 *
 * @return {void}
 */
const addPaymentMethodDropdowns = (): void => {
	// Use a centralized cache for user tokens.
	const cache = new UserTokenCache();

	// There should be a single element on the page, but still make sure to iterate over all of them.
	document
		.querySelectorAll< HTMLSpanElement >(
			'.wcpay-subscription-payment-method'
		)
		.forEach( ( element ) => {
			setupPaymentSelector( element, cache );
		} );
};

addPaymentMethodDropdowns();
