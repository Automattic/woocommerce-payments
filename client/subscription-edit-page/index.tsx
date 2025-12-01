/* global jQuery */

/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';

// TypeScript declaration for jQuery
declare const jQuery: (
	selector: any
) => {
	on: ( event: string, handler: () => void ) => void;
	off: ( event: string, handler: () => void ) => void;
};

/**
 * Internal dependencies
 */
import type {
	CachedUserData,
	PaymentMethodSelectProps,
	WCPayPMSelectorData,
	FetchUserTokensResponse,
} from './types';
import {
	startLoading,
	tokensLoaded,
	loadingFailed,
	hasEntry,
	getUserEntry,
	getDefaultTokenId,
} from './user-token-cache';

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
 * Add a listener to the customer select.
 *
 * This could be a shorter method, but because the customer select
 * element uses select2, it does not emit the typical `change` event.
 *
 * @param {(userId: number) => void} callback The callback to call when the customer is changed.
 * @return {() => void} The cleanup function.
 */
const addCustomerSelectListener = (
	cache: CachedUserData,
	ajaxUrl: string,
	nonce: string,
	setUser: ( userId: number ) => void,
	setCache: ( cache: CachedUserData ) => void
): ( () => void ) => {
	const element = document.getElementById( 'customer_user' );
	const customerUserSelect =
		element instanceof HTMLSelectElement ? element : null;

	if ( ! customerUserSelect ) {
		return (): void => {
			// No-op cleanup function when an element is not found.
		};
	}

	// Wrap in an internal callback to load the select's value and tokens.
	const internalCallback = async () => {
		const newUserId = parseInt( customerUserSelect.value, 10 ) || 0;

		setUser( newUserId );

		// Loaded, loading, or errored out, we do not need to load anything.
		if ( hasEntry( cache, newUserId ) ) {
			return;
		}

		const cacheWithLoading = startLoading( cache, newUserId );
		setCache( cacheWithLoading );

		try {
			const response = await fetchUserTokens( newUserId, ajaxUrl, nonce );
			if ( undefined === response ) {
				throw new Error(
					__(
						'Failed to fetch user tokens. Please reload the page and try again.',
						'woocommerce-payments'
					)
				);
			}

			setCache(
				tokensLoaded( cacheWithLoading, newUserId, response.tokens )
			);
		} catch ( error ) {
			setCache(
				loadingFailed(
					cacheWithLoading,
					newUserId,
					error instanceof Error
						? error.message
						: __( 'Unknown error', 'woocommerce-payments' )
				)
			);
		}
	};

	// Add the listener with the right technique, as select2 does not emit <select> events.
	jQuery( customerUserSelect ).on( 'select2:select', internalCallback );
	customerUserSelect.addEventListener( 'change', internalCallback );

	return () => {
		jQuery( customerUserSelect ).off( 'select2:select', internalCallback );
		customerUserSelect.removeEventListener( 'change', internalCallback );
	};
};

/**
 * Renders a payment method select or loading indicator.
 */
export const PaymentMethodSelect = ( {
	inputName,
	initialValue,
	initialUserId,
	initialCache,
	nonce,
	ajaxUrl,
}: PaymentMethodSelectProps ) => {
	const [ value, setValue ] = useState< number >( initialValue );
	const [ userId, setUserId ] = useState< number >( initialUserId );
	const [ cache, setCache ] = useState< CachedUserData >( initialCache );

	// If there is no value but a user, try to fall back to the default token.
	useEffect( () => {
		if ( 0 !== userId && value === 0 ) {
			const defaultTokenId = getDefaultTokenId( cache, userId );
			if ( value !== defaultTokenId ) {
				setValue( value );
			}
		}
	}, [ userId, value, cache ] );

	// Use the listener to update the user ID and cache.
	useEffect( () => {
		return addCustomerSelectListener(
			cache,
			ajaxUrl,
			nonce,
			setUserId,
			setCache
		);
	}, [ userId, cache, ajaxUrl, nonce ] );

	if ( userId <= 0 ) {
		return (
			<select name={ inputName } defaultValue={ 0 } key={ 'no-customer' }>
				<option value={ 0 } key={ 'no-customer' } disabled>
					{ __(
						'Please select a customer first',
						'woocommerce-payments'
					) }
				</option>
			</select>
		);
	}

	// There might be a user, but tokens could still be loading.
	const entry = getUserEntry( cache, userId );
	if ( undefined === entry || entry.loading ) {
		return <>{ __( 'Loading…', 'woocommerce-payments' ) }</>;
	} else if ( entry.loadingError ) {
		return <strong>{ entry.loadingError }</strong>;
	}

	return (
		// eslint-disable-next-line
		<select name={ inputName } defaultValue={ value } key={ userId }>
			{ 0 === value && (
				<option value={ 0 } key={ 'select' } disabled>
					{ __(
						'Please select a payment method',
						'woocommerce-payments'
					) }
				</option>
			) }
			{ entry.tokens.map( ( token ) => (
				<option value={ token.tokenId } key={ token.tokenId }>
					{ token.displayName }
				</option>
			) ) }
		</select>
	);
};

/**
 * Setup the payment method select for a given element.
 *
 * @param {HTMLSpanElement} element The <span> where the payment method select should be rendered.
 */
const setupPaymentSelector = ( element: HTMLSpanElement ): void => {
	const data = JSON.parse(
		element.getAttribute( 'data-wcpay-pm-selector' ) || '{}'
	) as WCPayPMSelectorData;

	// Use the values from the data instead of input to ensure correct types.
	const userId = data.userId ?? 0;
	const value = data.value ?? 0;

	// Initial population.
	const cache: CachedUserData = [];
	if ( userId ) {
		cache.push( {
			userId,
			loading: false,
			loadingError: null,
			tokens: data.tokens ?? [],
		} );
	}

	// In older Subscriptions versions, there was just a simple input.
	const input = element.querySelector( 'select,input' );
	if (
		! input ||
		! ( input instanceof HTMLSelectElement ) ||
		! ( input instanceof HTMLInputElement )
	) {
		return;
	}

	const root = createRoot( element );
	root.render(
		<PaymentMethodSelect
			inputName={ input.name }
			initialValue={ value }
			initialUserId={ userId }
			initialCache={ cache }
			nonce={ data.nonce }
			ajaxUrl={ data.ajaxUrl }
		/>
	);
};

/**
 * Initializes all payment method dropdown elements on the page.
 *
 * @return {void}
 */
const addPaymentMethodDropdowns = (): void => {
	document
		.querySelectorAll< HTMLSpanElement >(
			'.wcpay-subscription-payment-method'
		)
		.forEach( ( element ) => {
			setupPaymentSelector( element );
		} );
};

addPaymentMethodDropdowns();
