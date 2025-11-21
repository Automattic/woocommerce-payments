/* eslint-disable prettier/prettier */
/* global jQuery */

/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';

const generateInitialCache = ( initialUser, tokens ) => {
	return [
		{
			userId: initialUser,
			tokens,
		},
	];
};

const addToCache = ( cachedUserData, userId, tokens ) => {
	return [
		...cachedUserData,
		{
			userId,
			tokens,
		},
	];
};

const hasUserTokensInCache = ( cachedUserData, userId ) => {
	return cachedUserData.some( ( userData ) => userData.userId === userId );
};

const getUserTokensFromCache = ( cachedUserData, userId ) => {
	return cachedUserData.find( ( userData ) => userData.userId === userId )
		?.tokens;
};

const userHasToken = ( cachedUserData, userId, tokenId ) => {
	const userTokens = getUserTokensFromCache( cachedUserData, userId );

	if ( typeof userTokens === 'undefined' ) {
		return false;
	}

	return userTokens.some( ( token ) => token.tokenId === tokenId );
};

const fetchUserTokens = async ( userId, ajaxUrl, nonce ) => {
	const formData = new FormData();
	formData.append( 'action', 'wcpay_get_user_payment_tokens' );
	formData.append( 'nonce', nonce );
	formData.append( 'user_id', userId );

	const response = await fetch( ajaxUrl, {
		method: 'POST',
		body: formData,
	} );
	if ( ! response.ok ) {
		return undefined;
	}

	const result = await response.json();
	return result.data;
};

const addCustomerSelectListener = ( callback ) => {
	const customerUserSelect = document.getElementById( 'customer_user' );

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
} ) => {
	const [ selectValue, setSelectValue ] = useState( initialValue ?? 0 );
	const [ userId, setUserId ] = useState( initialUser ?? 0 );
	const [ cachedUserData, setCachedUserData ] = useState(
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
			const data = await fetchUserTokens(
				userId,
				ajaxUrl,
				nonce,
				setCachedUserData
			);
			setCachedUserData(
				addToCache( cachedUserData, userId, data.tokens )
			);
		} )();
	}, [ cachedUserData, userId, ajaxUrl, nonce ] );

	/**
	 * Generate options for the select.
	 */
	const options = [];
	if ( userId > 0 ) {
		const userTokens = getUserTokensFromCache( cachedUserData, userId );
		if ( typeof userTokens === 'undefined' ) {
			return __( 'Loading…', 'woocommerce-payments' );
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

const addWCPayCards = () => {
	document
		.querySelectorAll( '.wcpay-subscription-payment-method' )
		.forEach( ( element ) => {
			const data = JSON.parse( element.dataset.wcpayPmSelector );
			const inputName = element.querySelector( 'select,input' ).name;

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
