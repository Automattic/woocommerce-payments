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
	onCountryDropdownClick?: () => void;
	inputProps: {
		label: string;
		ariaLabel: string;
		name: string;
	};
	isBlocksCheckout: boolean;
}

const PhoneNumberInput = ( {
	onValueChange,
	value,
	onValidationChange = ( validation ) => validation,
	onCountryDropdownClick,
	inputProps = {
		label: '',
		ariaLabel: '',
		name: '',
	},
	isBlocksCheckout,
	...props
}: PhoneNumberInputProps ): JSX.Element => {
	const [ focusLost, setFocusLost ] = useState< boolean >( false );
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
			if ( iti && ( focusLost || iti.getNumber() ) ) {
				onValueChange( iti.getNumber() );
				onValidationChange( iti.isValidNumber() );
			}
		};

		let phoneCountries = {
			initialCountry: 'US',
			onlyCountries: [],
		};

		//if in admin panel
		if ( 'undefined' !== typeof wcpaySettings ) {
			const accountCountry = wcpaySettings?.accountStatus?.country ?? '';
			// Special case for Japan: Only Japanese phone numbers are accepted by Stripe
			if ( accountCountry === 'JP' ) {
				phoneCountries = {
					initialCountry: 'JP',
					// eslint-disable-next-line @typescript-eslint/ban-ts-comment
					// @ts-ignore
					onlyCountries: [ 'JP' ],
				};
			}
		}

		if ( currentRef ) {
			iti = intlTelInput( currentRef, {
				customPlaceholder: () => '',
				separateDialCode: true,
				hiddenInput: 'full',
				utilsScript: utils,
				dropdownContainer: document.body,
				...phoneCountries,
			} );
			setInputInstance( iti );

			currentRef.addEventListener( 'countrychange', handleCountryChange );

			const countryList = currentRef
				.closest( '.iti' )
				?.querySelector( '.iti__flag-container' );
			if ( countryList && onCountryDropdownClick ) {
				countryList.addEventListener( 'click', onCountryDropdownClick );
			}
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

				// Cleanup for country dropdown click event
				const countryList = currentRef
					?.closest( '.iti' )
					?.querySelector( '.iti__flag-container' );
				if ( countryList && onCountryDropdownClick ) {
					countryList.removeEventListener(
						'click',
						onCountryDropdownClick
					);
				}
			}
		};
	}, [
		onValueChange,
		onValidationChange,
		onCountryDropdownClick,
		focusLost,
	] );

	useEffect( () => {
		if (
			inputInstance &&
			inputRef.current &&
			( focusLost || inputInstance.getNumber() )
		) {
			onValidationChange( inputInstance.isValidNumber() );
		}
	}, [ value, inputInstance, inputRef, onValidationChange, focusLost ] );

	// Wrapping this in a div instead of a fragment because the library we're using for the phone input
	// alters the DOM and we'll get warnings about "removing content without using React."
	return (
		<div
			className={
				isBlocksCheckout ? 'wc-block-components-text-input' : ''
			}
		>
			<input
				type="tel"
				ref={ inputRef }
				value={ removeInternationalPrefix( value ) }
				onBlur={ () => {
					setFocusLost( true );
				} }
				onChange={ handlePhoneNumberInputChange }
				placeholder={ __( 'Mobile number', 'woocommerce-payments' ) }
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
				{ ...props }
			/>
		</div>
	);
};

export default PhoneNumberInput;
