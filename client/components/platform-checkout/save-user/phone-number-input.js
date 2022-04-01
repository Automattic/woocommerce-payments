/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import intlTelInput from 'intl-tel-input';

/**
 * Internal dependencies
 */
// eslint-disable-next-line import/no-unresolved
import utils from 'iti/utils';

const PhoneNumberInput = ( { handlePhoneNumberChange } ) => {
	const [ inputValue, setInputValue ] = useState(
		document.getElementById( 'billing_phone' )?.value ?? ''
	);
	const [ inputInstance, setInputInstance ] = useState( null );
	const [ isValid, setIsValid ] = useState( true );

	const handlePhoneNumberInputChange = ( e ) => {
		const value = e.target.value || inputValue;
		setInputValue( value );
		if ( inputInstance ) {
			handlePhoneNumberChange( inputInstance.getNumber() );
		}
	};

	const handlePhoneNumberValidation = () => {
		if ( inputInstance ) {
			setIsValid( inputInstance.isValidNumber() );
		} else {
			setIsValid( true );
		}
	};

	const ErrorText = () => {
		if ( ! isValid ) {
			const message = __(
				'Please enter a valid mobile phone number.',
				'woocommerce-payments'
			);

			return <p className="error-text">{ message }</p>;
		}
		return '';
	};

	useEffect( () => {
		// const formSubmitButton = document.getElementById( 'place_order' );
		const formSubmitButton = document.querySelector(
			'form.woocommerce-checkout button[type="submit"]'
		);

		if ( ! formSubmitButton ) {
			return;
		}

		const updateFormSubmitButton = () => {
			if ( isValid ) {
				formSubmitButton.removeAttribute( 'disabled' );
			} else {
				formSubmitButton.setAttribute( 'disabled', 'disabled' );
			}
		};

		updateFormSubmitButton();

		return () => {
			// Clean up
			formSubmitButton.removeAttribute( 'disabled' );
		};
	}, [ isValid ] );

	useEffect( () => {
		let iti = null;
		const input = document.querySelector(
			`input[aria-label="${ __(
				'Mobile phone number',
				'woocommerce-payments'
			) }"]`
		);

		const handleCountryChange = () => {
			handlePhoneNumberChange( iti.getNumber() );
		};

		if ( input ) {
			iti = intlTelInput( input, {
				initialCountry: 'US',
				customPlaceholder: () => '',
				separateDialCode: true,
				hiddenInput: 'full',
				utilsScript: utils,
			} );
			setInputInstance( iti );

			// Focus the phone number input when the component loads.
			input.focus();

			input.addEventListener( 'countrychange', handleCountryChange );
		}

		return () => {
			if ( iti ) {
				iti.destroy();
				input.removeEventListener(
					'countrychange',
					handleCountryChange
				);
			}
		};
	}, [ handlePhoneNumberChange ] );

	return (
		<>
			<input
				type="tel"
				aria-label={ __(
					'Mobile phone number',
					'woocommerce-payments'
				) }
				label={ __( 'Mobile phone number', 'woocommerce-payments' ) }
				name="platform_checkout_user_phone_field[no-country-code]"
				value={ inputValue }
				onChange={ handlePhoneNumberInputChange }
				onBlur={ handlePhoneNumberValidation }
				className={
					! isValid
						? 'phone-input input-text has-error'
						: 'phone-input input-text'
				}
			/>
			<ErrorText />
		</>
	);
};

export default PhoneNumberInput;
