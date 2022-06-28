/**
 * External dependencies
 */
import React, { useEffect, useState, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import intlTelInput from 'intl-tel-input';
import './style.scss';

/**
 * Internal dependencies
 */
// eslint-disable-next-line import/no-unresolved
import utils from 'iti/utils';

const PhoneNumberInput = ( {
	onValueChange,
	value,
	onValidationChange = () => {},
	inputProps = {},
} ) => {
	const [ inputInstance, setInputInstance ] = useState( null );
	const inputRef = useRef();

	const handlePhoneNumberInputChange = () => {
		if ( inputInstance ) {
			onValueChange( inputInstance.getNumber() );
			onValidationChange( inputInstance.isValidNumber() );
		}
	};

	const removeInternationalPrefix = ( phone ) => {
		if ( inputInstance ) {
			return phone.replace(
				'+' + inputInstance.getSelectedCountryData().dialCode,
				''
			);
		}

		return value;
	};

	useEffect( () => {
		let iti = null;
		const currentRef = inputRef.current;

		const handleCountryChange = () => {
			onValueChange( iti.getNumber() );
			onValidationChange( iti.isValidNumber() );
		};

		if ( currentRef ) {
			iti = intlTelInput( currentRef, {
				initialCountry: 'US',
				customPlaceholder: () => '',
				separateDialCode: true,
				hiddenInput: 'full',
				utilsScript: utils,
			} );
			setInputInstance( iti );
			onValidationChange( iti.isValidNumber() );

			// Focus the phone number input when the component loads.
			currentRef.focus();

			currentRef.addEventListener( 'countrychange', handleCountryChange );
		}

		return () => {
			if ( iti ) {
				iti.destroy();
				currentRef.removeEventListener(
					'countrychange',
					handleCountryChange
				);
			}
		};
	}, [ onValueChange, onValidationChange ] );

	// Wrapping this in a div instead of a fragment because the library we're using for the phone input
	// alters the DOM and we'll get warnings about "removing content without using React."
	return (
		<div>
			<input
				type="tel"
				ref={ inputRef }
				value={ removeInternationalPrefix( value ) }
				onChange={ handlePhoneNumberInputChange }
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
					inputInstance && ! inputInstance.isValidNumber()
						? 'phone-input input-text has-error'
						: 'phone-input input-text'
				}
			/>
		</div>
	);
};

export default PhoneNumberInput;
