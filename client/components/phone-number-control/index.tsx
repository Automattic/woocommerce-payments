/* eslint-disable jsx-a11y/no-onchange */
/**
 * External dependencies
 */
import React, { useState, useRef } from 'react';
import { BaseControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';
import 'intl-tel-input';

/**
 * Internal dependencies
 */
import './style.scss';
import { useUniqueId } from 'hooks/use-unique-id';

// Rely on the global variable set by the intl-tel-input library.
// It can by replaced with a static list of country/codes if needed.
const countryCodes = window.intlTelInputGlobals
	.getCountryData()
	.reduce( ( acc, { dialCode, iso2 } ) => {
		acc[ iso2.toLocaleUpperCase() ] = `+${ dialCode }`;
		return acc;
	}, {} as Record< string, string > );

interface Props {
	value: string;
	onChange: ( value: string ) => void;
	className?: string;
	label?: string;
	help?: string;
}

const PhoneNumberControl: React.FC< Props > = ( {
	value,
	onChange,
	...rest
} ) => {
	const [ focused, setFocused ] = useState( false );
	const inputRef = useRef< HTMLInputElement >( null );
	const id = useUniqueId( 'wcpay-phone-number-control-' );

	const [ countryCode, setCountryCode ] = useState(
		wcpaySettings.connect.country || 'US'
	);
	const phoneNumber = value.replace( countryCodes[ countryCode ], '' );

	const handleFocus = () => inputRef.current?.focus();

	const handleChange = ( code: string, number: string ) => {
		setCountryCode( code );
		onChange( `${ countryCodes[ code ] }${ number }` );
		handleFocus();
	};

	const handleSelect = ( event: React.ChangeEvent< HTMLSelectElement > ) => {
		handleChange( event.target.value, phoneNumber );
	};

	const handleInput = ( event: React.ChangeEvent< HTMLInputElement > ) => {
		handleChange( countryCode, event.target.value.replace( /\D/g, '' ) );
	};

	return (
		<BaseControl id={ id } { ...rest }>
			<div
				className={ classNames(
					'wcpay-component-phone-number-control',
					'components-text-control__input',
					{
						focused,
					}
				) }
			>
				<select
					value={ countryCode }
					onChange={ handleSelect }
					aria-label={ __(
						'phone number country code',
						'woocommerce-payments'
					) }
				>
					{ Object.keys( countryCodes )
						.sort()
						.map( ( key ) => (
							<option key={ key } value={ key }>
								{ key }
							</option>
						) ) }
				</select>
				<button tabIndex={ -1 } onClick={ handleFocus }>
					{ countryCodes[ countryCode ] }
				</button>
				<input
					id={ id }
					ref={ inputRef }
					type="text"
					value={ phoneNumber }
					onChange={ handleInput }
					onFocus={ () => setFocused( true ) }
					onBlur={ () => setFocused( false ) }
				/>
			</div>
		</BaseControl>
	);
};

export default PhoneNumberControl;
