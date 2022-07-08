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
import utils from 'iti/utils';

interface PhoneNumberInputProps {
	value: string;
	onValidationChange: ( isValid: boolean ) => void;
	onValueChange: ( value: string ) => void;
	inputProps: {
		label: string;
		ariaLabel: string;
		name: string;
	};
}

const PhoneNumberInput = ( {
	onValueChange,
	value,
	onValidationChange = ( validation ) => validation,
	inputProps = {
		label: '',
		ariaLabel: '',
		name: '',
	},
}: PhoneNumberInputProps ): JSX.Element => {
	const [
		inputInstance,
		setInputInstance,
	] = useState< intlTelInput.Plugin | null >( null );
	const inputRef = useRef< HTMLInputElement >( null );

	const handlePhoneNumberInputChange = () => {
		if ( inputInstance ) {
			onValueChange( inputInstance.getNumber() );
			onValidationChange( inputInstance.isValidNumber() );
		}
	};

	const removeInternationalPrefix = ( phone: string ) => {
		if ( inputInstance ) {
			return phone.replace(
				'+' + inputInstance.getSelectedCountryData().dialCode,
				''
			);
		}

		return phone;
	};

	useEffect( () => {
		let iti: intlTelInput.Plugin | null = null;
		const currentRef = inputRef.current;

		const handleCountryChange = () => {
			if ( iti ) {
				onValueChange( iti.getNumber() );
				onValidationChange( iti.isValidNumber() );
			}
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

			currentRef.addEventListener( 'countrychange', handleCountryChange );
		}

		return () => {
			if ( iti ) {
				iti.destroy();

				if ( currentRef ) {
					currentRef.removeEventListener(
						'countrychange',
						handleCountryChange
					);
				}
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
				aria-label={
					inputProps.ariaLabel ||
					__( 'Mobile phone number', 'woocommerce-payments' )
				}
				name={ inputProps.name }
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
