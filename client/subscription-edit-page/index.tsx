/* eslint-disable prettier/prettier */
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
	Token,
	CachedUserData,
	PaymentMethodSelectProps,
	WCPayPMSelectorData,
	FetchUserTokensResponse,
} from './types';

const generateInitialCache = (
	initialUser: number | undefined,
	tokens: Token[]
): CachedUserData => {
	const data = [];

	if ( initialUser !== undefined ) {
		data.push( {
			userId: initialUser,
			tokens: [ ...tokens ],
		} );
	}

	return data;
};

const addToCache = (
	cachedUserData: CachedUserData,
	userId: number,
	tokens: Token[]
): CachedUserData => {
	return [
		...cachedUserData,
		{
			userId,
			tokens,
		},
	];
};

const hasUserTokensInCache = (
	cachedUserData: CachedUserData,
	userId: number
): boolean => {
	return cachedUserData.some( ( userData ) => userData.userId === userId );
};

const getUserTokensFromCache = (
	cachedUserData: CachedUserData,
	userId: number
): Token[] => {
	return (
		cachedUserData.find( ( userData ) => userData.userId === userId )
			?.tokens ?? []
	);
};

const userHasToken = (
	cachedUserData: CachedUserData,
	userId: number,
	tokenId: number
): boolean => {
	const userTokens = getUserTokensFromCache( cachedUserData, userId );
	return userTokens.some( ( token ) => token.tokenId === tokenId );
};

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

const PaymentMethodSelect = ( {
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
		if ( hasUserTokensInCache( cachedUserData, userId ) ) {
			return;
		}

		( async () => {
			const data = await fetchUserTokens( userId, ajaxUrl, nonce );

			if ( data ) {
				setCachedUserData(
					addToCache( cachedUserData, userId, data.tokens )
				);
			}
		} )();
	}, [ cachedUserData, userId, ajaxUrl, nonce ] );

	/**
	 * Generate options for the select.
	 */
	const options: JSX.Element[] = [];
	if ( userId > 0 ) {
		const userTokens = getUserTokensFromCache( cachedUserData, userId );
		if ( typeof userTokens === 'undefined' ) {
			return <span>{ __( 'Loading…', 'woocommerce-payments' ) }</span>;
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

		userTokens.forEach( ( token ) => {
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
