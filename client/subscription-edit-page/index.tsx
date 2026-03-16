/* global jQuery */

/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type {
	PaymentMethodSelectProps,
	WCPayPMSelectorData,
	Token,
} from './types';

/**
 * Cache for all tokens, keyed by "userId-gatewayId" to support multiple gateways.
 */
const cachedTokens = new Map< string, Token[] >();

/**
 * Generates a cache key for tokens.
 *
 * @param {number} userId    The user ID.
 * @param {string} gatewayId The gateway ID.
 * @return {string} The cache key.
 */
const getCacheKey = ( userId: number, gatewayId: string ): string => {
	return `${ userId }-${ gatewayId }`;
};

/**
 * Clears the token cache. Exported for testing purposes.
 */
export const clearTokenCache = (): void => {
	cachedTokens.clear();
};

/**
 * Fetch the tokens for a user from the back-end.
 *
 * @param {number}  userId    The user ID.
 * @param {string}  ajaxUrl   The AJAX URL.
 * @param {string}  nonce     The nonce.
 * @param {string} gatewayId Gateway ID to filter tokens.
 * @return {Promise<Token[]>} The tokens for the user.
 * @throws {Error} If the tokens cannot be fetched or the response is invalid.
 */
export const fetchUserTokens = async (
	userId: number,
	ajaxUrl: string,
	nonce: string,
	gatewayId: string
): Promise< Token[] > => {
	const formData = new FormData();
	formData.append( 'action', 'wcpay_get_user_payment_tokens' );
	formData.append( 'nonce', nonce );
	formData.append( 'user_id', userId.toString() );
	formData.append( 'gateway_id', gatewayId );

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
	const data = result.data as { tokens: Token[] };

	if ( undefined === data ) {
		throw new Error(
			__(
				'Failed to fetch user tokens. Please reload the page and try again.',
				'woocommerce-payments'
			)
		);
	}

	return data.tokens;
};

/**
 * Add a listener to the customer select.
 *
 * This could be a shorter method, but because the customer select
 * element uses select2, it does not emit the typical `change` event.
 *
 * @return {() => void} The cleanup function.
 */
export const addCustomerSelectListener = (
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

	// Wrap in an internal callback to load the select's value and tokens.
	const internalCallback = async () => {
		callback( parseInt( customerUserSelect.value, 10 ) || 0 );
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
 * Get the default token for a user and gateway.
 *
 * @param {number}  userId    The user ID.
 * @param {string} gatewayId The gateway ID.
 * @return {number} The default token ID or 0 if no default token is found.
 */
const getDefaultUserToken = ( userId: number, gatewayId: string ): number => {
	const cacheKey = getCacheKey( userId, gatewayId );
	const userTokens = cachedTokens.get( cacheKey );
	if ( undefined === userTokens ) {
		return 0;
	}

	const defaultToken = userTokens.find( ( token ) => token.isDefault );
	return undefined !== defaultToken ? defaultToken.tokenId : 0;
};

/**
 * Renders a payment method select or loading indicator.
 */
export const PaymentMethodSelect = ( {
	inputName,
	initialValue,
	initialUserId,
	nonce,
	ajaxUrl,
	gatewayId,
}: PaymentMethodSelectProps ) => {
	const [ value, setValue ] = useState< number >( initialValue );
	const [ userId, setUserId ] = useState< number >( initialUserId );

	const [ isLoading, setIsLoading ] = useState< boolean >( false );
	const [ loadingError, setLoadingError ] = useState< string | null >( null );

	useEffect( () => {
		return addCustomerSelectListener( async ( newUserId ) => {
			const cacheKey = getCacheKey( newUserId, gatewayId );
			const newValue = getDefaultUserToken( newUserId, gatewayId );
			setValue( newValue );
			setUserId( newUserId );

			// Loaded, loading, or errored out, we do not need to load anything.
			if ( cachedTokens.has( cacheKey ) ) {
				return;
			}

			setIsLoading( true );
			try {
				const tokens = await fetchUserTokens(
					newUserId,
					ajaxUrl,
					nonce,
					gatewayId
				);

				cachedTokens.set( cacheKey, tokens );
				const defaultToken = getDefaultUserToken(
					newUserId,
					gatewayId
				);
				if ( newValue !== defaultToken ) {
					setValue( defaultToken );
				}
				setIsLoading( false );
				setLoadingError( null );
			} catch ( error ) {
				setIsLoading( false );
				setLoadingError(
					error instanceof Error
						? error.message
						: __( 'Unknown error', 'woocommerce-payments' )
				);
			}
		} );
	}, [ ajaxUrl, nonce, gatewayId ] );

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

	if ( isLoading ) {
		return <>{ __( 'Loading…', 'woocommerce-payments' ) }</>;
	}

	if ( loadingError ) {
		return <strong>{ loadingError }</strong>;
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
			{ cachedTokens
				.get( getCacheKey( userId, gatewayId ) )
				?.map( ( token ) => (
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
	const gatewayId = data.gatewayId ?? 'woocommerce_payments';

	if ( userId ) {
		// Initial cache population.
		cachedTokens.set( getCacheKey( userId, gatewayId ), data.tokens ?? [] );
	}

	// In older Subscriptions versions, there was just a simple input.
	const input = element.querySelector( 'select,input' );
	if (
		! input ||
		! (
			input instanceof HTMLSelectElement ||
			input instanceof HTMLInputElement
		)
	) {
		return;
	}

	const root = createRoot( element );
	root.render(
		<PaymentMethodSelect
			inputName={ input.name }
			initialValue={ value }
			initialUserId={ userId }
			nonce={ data.nonce }
			ajaxUrl={ data.ajaxUrl }
			gatewayId={ gatewayId }
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
