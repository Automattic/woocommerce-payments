/* eslint-disable prettier/prettier */
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
	Token,
	CachedUserData,
	CachedUserDataItem,
	PaymentMethodSelectProps,
	WCPayPMSelectorData,
	FetchUserTokensResponse,
} from './types';

/**
 * Add a listener to the customer select.
 *
 * @param callback The callback to call when the customer is changed.
 * @return The cleanup function.
 */
const addCustomerSelectListener = (
	callback: ( userId: number ) => void
): ( () => void ) => {
	const customerUserSelect = document.getElementById(
		'customer_user'
	) as HTMLSelectElement | null;

	if ( ! customerUserSelect ) {
		return (): void => {
			// No-op cleanup function when element is not found
		};
	}

	// Wrap in an internal callback to load the select's value.
	const internalCallback = () =>
		callback( parseInt( customerUserSelect.value, 10 ) || 0 );

	// Add the listner with the right technique, as select2 does not emit <select> events.
	jQuery( customerUserSelect ).on( 'select2:select', internalCallback );
	customerUserSelect.addEventListener( 'change', internalCallback );

	// If the effect is unmounted, remove the listener.
	return () => {
		jQuery( customerUserSelect ).off( 'select2:select', internalCallback );
		customerUserSelect.removeEventListener( 'change', internalCallback );
	};
};

/**
 * Generates the initial user-token cache in a proper format.
 *
 * @param initialUser Initial user ID.
 * @param tokens The pre-loaded tokens.
 * @return The initial cached data.
 */
export const generateInitialCache = (
	initialUser: number | undefined,
	tokens: Token[]
): CachedUserData => {
	const data = [];

	if ( initialUser !== undefined ) {
		data.push( {
			userId: initialUser,
			tokens: [ ...tokens ],
			loading: false,
			loadingError: null,
		} );
	}

	return data;
};

/**
 * Add a new entry for a new user in the cache.
 * The new entry can only land in a loading state.
 *
 * @param cachedUserData Existing cached data.
 * @param userId The user ID.
 * @return The cached data with the loading state.
 */
export const addLoadingState = (
	cachedUserData: CachedUserData,
	userId: number
): CachedUserData => {
	return [
		...cachedUserData,
		{
			userId,
			loading: true,
			loadingError: null,
			tokens: [],
		},
	];
};

/**
 * Update the cached data for a user when the tokens are loaded.
 *
 * @param cachedUserData Existing cached data.
 * @param userId The user ID.
 * @param tokens The loaded tokens.
 * @return The cached data with the tokens and the loading state removed.
 */
export const userTokensLoaded = (
	cachedUserData: CachedUserData,
	userId: number,
	tokens: Token[]
): CachedUserData => {
	return cachedUserData.map( ( userData ) => {
		if ( userData.userId !== userId ) {
			return userData;
		}

		return {
			...userData,
			tokens,
			loading: false,
			loadingError: null,
		};
	} );
};

/**
 * Update the cached data for a user when loading the tokens for a user failed.
 *
 * @param cachedUserData Existing cached data.
 * @param userId The user ID.
 * @param errorMessage The error message.
 * @return The cached data with the loading state removed and the error message set.
 */
export const userTokensLoadingFailed = (
	cachedUserData: CachedUserData,
	userId: number,
	errorMessage: string
): CachedUserData => {
	return cachedUserData.map( ( userData ) => {
		if ( userData.userId !== userId ) {
			return userData;
		}
		return {
			...userData,
			loading: false,
			loadingError: errorMessage,
		};
	} );
};

/**
 * Check if the cached data for a user contains tokens.
 *
 * @param cachedUserData Existing cached data.
 * @param userId The user ID.
 * @return True if the cached data for the user contains tokens, false otherwise.
 */
export const userHasEntryInCache = (
	cachedUserData: CachedUserData,
	userId: number
): boolean => {
	return cachedUserData.some( ( userData ) => userData.userId === userId );
};

/**
 * Get the user entry from the cached data.
 *
 * @param cachedUserData Existing cached data.
 * @param userId The user ID.
 * @return The user entry.
 */
const getUserEntryFromCache = (
	cachedUserData: CachedUserData,
	userId: number
): CachedUserDataItem | undefined => {
	return cachedUserData.find( ( userData ) => userData.userId === userId );
};

/**
 * Get the tokens for a user from the cached data.
 *
 * @param cachedUserData Existing cached data.
 * @param userId The user ID.
 * @return The tokens for the user.
 */
export const getUserTokensFromCache = (
	cachedUserData: CachedUserData,
	userId: number
): Token[] => {
	return (
		cachedUserData.find( ( userData ) => userData.userId === userId )
			?.tokens ?? []
	);
};

/**
 * Check if a user has a specific token.
 *
 * @param cachedUserData Existing cached data.
 * @param userId The user ID.
 * @param tokenId The token ID.
 * @return True if the user has the token, false otherwise.
 */
export const userHasToken = (
	cachedUserData: CachedUserData,
	userId: number,
	tokenId: number
): boolean => {
	const userTokens = getUserTokensFromCache( cachedUserData, userId );
	return userTokens.some( ( token ) => token.tokenId === tokenId );
};

/**
 * Fetch the tokens for a user from the server.
 *
 * @param userId The user ID.
 * @param ajaxUrl The AJAX URL.
 * @param nonce The nonce.
 * @return The tokens for the user.
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

export const PaymentMethodSelect = ( {
	inputName,
	initialValue,
	initialUser,
	tokens,
	ajaxUrl,
	nonce,
}: PaymentMethodSelectProps ) => {
	const [ selectValue, setSelectValue ] = useState< number >(
		initialValue ?? 0
	);
	const [ userId, setUserId ] = useState< number >( initialUser ?? 0 );
	const [ cachedUserData, setCachedUserData ] = useState< CachedUserData >(
		generateInitialCache( initialUser, tokens )
	);

	useEffect( () =>
		addCustomerSelectListener( ( newUserId ) => {
			setUserId( newUserId );
			// Once the customer is changed, the selected payment method is lost.
			setSelectValue( 0 );
		} )
	);

	useEffect( () => {
		// Loader, loading, or errored out, we do not need to load anything.
		if ( userHasEntryInCache( cachedUserData, userId ) ) {
			return;
		}

		const dataWithLoadingState = addLoadingState( cachedUserData, userId );
		setCachedUserData( dataWithLoadingState );

		( async () => {
			try {
				const data = await fetchUserTokens( userId, ajaxUrl, nonce );
				if ( undefined === data ) {
					throw new Error(
						__(
							'Failed to fetch user tokens. Please reload the page and try again.',
							'woocommerce-payments'
						)
					);
				}
				setCachedUserData(
					userTokensLoaded(
						dataWithLoadingState,
						userId,
						data.tokens
					)
				);
			} catch ( error ) {
				const errorMessage =
					error instanceof Error
						? error.message
						: __( 'Unknown error', 'woocommerce-payments' );
				setCachedUserData(
					userTokensLoadingFailed(
						dataWithLoadingState,
						userId,
						errorMessage
					)
				);
			}
		} )();
	}, [ cachedUserData, userId, ajaxUrl, nonce ] );

	/**
	 * Generate options for the select.
	 */
	const options: JSX.Element[] = [];
	if ( userId > 0 ) {
		const entry = getUserEntryFromCache( cachedUserData, userId );
		if ( undefined === entry || entry.loading ) {
			return <>{ __( 'Loading…', 'woocommerce-payments' ) }</>;
		} else if ( entry.loadingError ) {
			return <strong>{ entry.loadingError }</strong>;
		}

		if ( ! userHasToken( cachedUserData, userId, selectValue ) ) {
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
			value={ selectValue }
			onChange={ ( event ) =>
				setSelectValue( parseInt( event.target.value, 10 ) )
			}
		>
			{ options }
		</select>
	);
};

const addWCPayCards = (): void => {
	document
		.querySelectorAll( '.wcpay-subscription-payment-method' )
		.forEach( ( element ) => {
			const data = JSON.parse(
				element.getAttribute( 'data-wcpay-pm-selector' ) || '{}'
			) as WCPayPMSelectorData;
			const inputElement = element.querySelector( 'select,input' ) as
				| HTMLSelectElement
				| HTMLInputElement
				| null;
			if ( ! inputElement ) {
				return;
			}
			const inputName = inputElement.name;

			createRoot( element ).render(
				<PaymentMethodSelect
					inputName={ inputName }
					initialValue={ data.value }
					initialUser={ data.userId }
					tokens={ data.tokens }
					ajaxUrl={ data.ajaxUrl }
					nonce={ data.nonce }
				/>
			);
		} );
};

addWCPayCards();
