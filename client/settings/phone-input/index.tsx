/**
 * External dependencies
 */
import React, { useEffect, useState, useRef } from 'react';
import { __ } from '@wordpress/i18n';
// TODO: Remove full path once we find a way to enqueue wc-components in pages other than admin.
import PhoneNumberInput from '@woocommerce/components/build/phone-number-input';
import { validatePhoneNumber } from '@woocommerce/components/build/phone-number-input/validation';
import './style.scss';

interface PhoneInputProps {
	value: string;
	onValidationChange: ( isValid: boolean ) => void;
	onValueChange: ( value: string ) => void;
	onCountryDropdownClick?: () => void;
	inputProps: {
		label: string;
		ariaLabel: string;
		name: string;
		id: string;
	};
	isBlocksCheckout: boolean;
}

const PhoneInput = ( {
	onValueChange,
	value,
	onValidationChange = ( validation ) => validation,
	onCountryDropdownClick,
	inputProps = {
		label: '',
		ariaLabel: '',
		name: '',
		id: '',
	},
	isBlocksCheckout,
	...props
}: PhoneInputProps ): JSX.Element => {
	const [ focusLost, setFocusLost ] = useState< boolean >( false );
	const [ countryCode, setCountryCode ] = useState< string >( '' );
	const divRef = useRef< HTMLInputElement >( null );

	const handlePhoneInputChange = (
		newValue: string,
		e164: string,
		country: string
	) => {
		const nationalNumber = newValue.replace( /\+\d+\s/, '' );

		setCountryCode( country );

		if ( nationalNumber ) {
			onValueChange( e164 );
		} else {
			onValueChange( '' );
		}
		if ( focusLost ) {
			onValidationChange( validatePhoneNumber( e164, country ) );
		}
	};

	// TODO: ideally PhoneNumberInput should provide a way to forward these listeners.
	useEffect( () => {
		const cleanupCallbacks: ( () => void )[] = [];
		const inputNode = divRef.current?.querySelector( 'input' );
		if ( inputNode ) {
			const onBlur = () => {
				setFocusLost( true );
				onValidationChange( validatePhoneNumber( value, countryCode ) );
			};
			inputNode.addEventListener( 'blur', onBlur );
			cleanupCallbacks.push( () => {
				inputNode.removeEventListener( 'blur', onBlur );
			} );
		}

		const buttonNode = divRef.current?.querySelector( 'button' );
		if ( buttonNode ) {
			const onClick = () => {
				if ( onCountryDropdownClick ) {
					onCountryDropdownClick();
				}
			};
			buttonNode.addEventListener( 'click', onClick );
			cleanupCallbacks.push( () => {
				buttonNode.removeEventListener( 'click', onClick );
			} );
		}

		return () => {
			cleanupCallbacks.forEach( ( cb ) => cb() );
		};
	}, [
		divRef,
		onCountryDropdownClick,
		onValidationChange,
		value,
		countryCode,
	] );

	// Wrapping this in a div instead of a fragment because the library we're using for the phone input
	// alters the DOM and we'll get warnings about "removing content without using React."
	return (
		<div
			ref={ divRef }
			className={
				isBlocksCheckout ? 'wc-block-components-text-input' : ''
			}
		>
			<PhoneNumberInput
				value={ value }
				onChange={ handlePhoneInputChange }
				name={ inputProps.name }
				id={ inputProps.id }
				className="phone-input"
				{ ...props }
			/>
		</div>
	);
};

export default PhoneInput;
