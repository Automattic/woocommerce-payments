/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import intlTelInput from 'intl-tel-input';

/**
 * Internal dependencies
 */
// eslint-disable-next-line import/no-unresolved
import utils from 'iti/utils';

const PhoneNumberInput = ( { handlePhoneNumberChange } ) => {
	const [ inputValue, setInputValue ] = useState( '' );
	const [ inputInstance, setInputInstance ] = useState( null );

	const handlePhoneNumberInputChange = ( value = inputValue ) => {
		setInputValue( value );
		if ( inputInstance ) {
			handlePhoneNumberChange( inputInstance.getNumber() );
		}
	};

	useEffect( () => {
		let iti = null;
		const input = document.querySelector( '#phone-number' );

		const handleCountryChange = () => {
			handlePhoneNumberChange( iti.getNumber() );
		};

		if ( input ) {
			iti = intlTelInput( input, {
				initialCountry: 'US',
				customPlaceholder: function ( selectedCountryPlaceholder ) {
					return selectedCountryPlaceholder;
				},
				separateDialCode: true,
				utilsScript: utils,
			} );
			setInputInstance( iti );

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
		<TextControl
			type="tel"
			id="phone-number"
			label={ __( 'Mobile phone number', 'woocommerce-payments' ) }
			value={ inputValue }
			onChange={ handlePhoneNumberInputChange }
		/>
	);
};

export default PhoneNumberInput;
