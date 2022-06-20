/**
 * External dependencies
 */
import React, { useEffect, useState, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import intlTelInput from 'intl-tel-input';

/**
 * Internal dependencies
 */
// eslint-disable-next-line import/no-unresolved
import utils from 'iti/utils';

const PhoneNumberInput = ( { onValueChange, value, inputProps = {} } ) => {
	const [ inputValue, setInputValue ] = useState( value || '' );
	const [ inputInstance, setInputInstance ] = useState( null );
	const [ isValid, setIsValid ] = useState( true );
	const inputRef = useRef();

	const handlePhoneNumberInputChange = ( e ) => {
		setInputValue( e.target.value );
		if ( inputInstance ) {
			onValueChange( inputInstance.getNumber() );
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

		const handleCountryChange = () => {
			onValueChange( iti.getNumber() );
		};

		if ( inputRef.current ) {
			iti = intlTelInput( inputRef.current, {
				initialCountry: 'US',
				customPlaceholder: () => '',
				separateDialCode: true,
				hiddenInput: 'full',
				utilsScript: utils,
			} );
			setInputInstance( iti );
			setInputValue( ( currentInputValue ) =>
				currentInputValue.replace(
					'+' + iti.getSelectedCountryData().dialCode,
					''
				)
			);

			// Focus the phone number input when the component loads.
			inputRef.current.focus();

			inputRef.current.addEventListener(
				'countrychange',
				handleCountryChange
			);
		}

		return () => {
			if ( iti ) {
				iti.destroy();
				inputRef.removeEventListener(
					'countrychange',
					handleCountryChange
				);
			}
		};
	}, [ onValueChange ] );

	// Wrapping this in a div instead of a fragment because the library we're using for the phone input
	// alters the DOM and we'll get warnings about "removing content without using React."
	return (
		<div>
			<input
				type="tel"
				ref={ inputRef }
				value={ inputValue }
				onChange={ handlePhoneNumberInputChange }
				onBlur={ handlePhoneNumberValidation }
				label={
					inputProps.label ||
					__( 'Mobile phone number', 'woocommerce-payments' )
				}
				aria-label={
					inputProps.ariaLabel ||
					__( 'Mobile phone number', 'woocommerce-payments' )
				}
				name={
					inputProps.name ||
					'platform_checkout_user_phone_field[no-country-code]'
				}
				className={
					! isValid
						? 'phone-input input-text has-error'
						: 'phone-input input-text'
				}
			/>
			<ErrorText />
		</div>
	);
};

export default PhoneNumberInput;
