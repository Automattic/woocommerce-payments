/* global jQuery */

/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { __ } from '@wordpress/i18n';

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
	const [ cachedUserData, setCachedUserData ] = useState( [
		{
			userId: initialUser,
			tokens,
		},
	] );

	const options = [];

	/**
	 * Listen for customer changes.
	 */
	useEffect( () => {
		const customerUserSelect = document.getElementById( 'customer_user' );

		const handler = () => {
			setUserId( parseInt( customerUserSelect.value, 10 ) || 0 );
			setSelectValue( 0 );
		};

		// Add the listner with the right technique, as select2 does not emit <select> events.
		jQuery( customerUserSelect ).on( 'select2:select', handler );
		customerUserSelect.addEventListener( 'change', handler );

		// If the effect is unmounted, remove the listener.
		return () => {
			jQuery( customerUserSelect ).off( 'select2:select', handler );
			customerUserSelect.removeEventListener( 'change', handler );
		};
	} );

	/**
	 * When the customer changes and methods are not loaded, load them.
	 */
	useEffect( () => {
		const inCache = cachedUserData.some(
			( userData ) => userData.userId === userId
		);
		if ( inCache ) {
			return;
		}

		jQuery.post(
			ajaxUrl,
			{
				action: 'wcpay_get_user_payment_tokens',
				nonce: nonce,
				user_id: userId,
			},
			( { data } ) => {
				setCachedUserData( [
					...cachedUserData,
					{
						userId: userId,
						tokens: data.tokens,
					},
				] );
			}
		);
	}, [ cachedUserData, userId, ajaxUrl, nonce ] );

	if ( userId > 0 ) {
		const userTokens = cachedUserData.find(
			( userData ) => userData.userId === userId
		)?.tokens;

		if ( typeof userTokens === 'undefined' ) {
			return __( 'Loading…', 'woocommerce-payments' );
		}

		const currentValueIsValid = userTokens.some(
			( token ) => token.tokenId === selectValue
		);

		if ( ! currentValueIsValid ) {
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
