/* eslint-disable jsx-a11y/no-onchange */
/**
 * External dependencies
 */
import React, { useState, useRef, useLayoutEffect } from 'react';
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
	onChange: ( value: string, country: string ) => void;
	onBlur?: () => void;
	country?: string;
	className?: string;
	label?: string;
	help?: string;
}

const PhoneNumberControl: React.FC< Props > = ( {
	value,
	country,
	onChange,
	onBlur,
	...rest
} ) => {
	const [ focused, setFocused ] = useState( false );
	const [ spanWidth, setSpanWidth ] = useState( 0 );
	const spanRef = useRef< HTMLSpanElement >( null );
	const inputRef = useRef< HTMLInputElement >( null );
	const id = useUniqueId( 'wcpay-phone-number-control-' );

	const [ countryCode, setCountryCode ] = useState( country || 'US' );
	const phoneNumber = value.replace( countryCodes[ countryCode ], '' );

	useLayoutEffect( () => {
		if ( spanRef.current ) {
			setSpanWidth( spanRef.current.offsetWidth + 1 );
		}
	}, [ spanRef, countryCode ] );

	const handleFocus = () => inputRef.current?.focus();

	const handleChange = ( code: string, number: string ) => {
		setCountryCode( code );
		onChange( `${ countryCodes[ code ] }${ number }`, code );
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
				<svg xmlns="http://www.w3.org/2000/svg">
					<path d="M13.125 8.7 9 12l-4.125-3.3.675-.9L9 10.5l3.375-2.7.75 .9z" />
				</svg>
				<span ref={ spanRef }>{ countryCodes[ countryCode ] }</span>
				<input
					id={ id }
					ref={ inputRef }
					type="text"
					value={ phoneNumber }
					onChange={ handleInput }
					onFocus={ () => setFocused( true ) }
					onBlur={ () => {
						setFocused( false );
						onBlur?.();
					} }
					style={ {
						paddingLeft: spanWidth + 8,
						marginLeft: -spanWidth,
					} }
				/>
			</div>
		</BaseControl>
	);
};

export default PhoneNumberControl;

export type { Props as PhoneNumberControlProps };
