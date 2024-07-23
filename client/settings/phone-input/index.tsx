/**
 * External dependencies
 */
import React from 'react';
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
	const handlePhoneInputChange = (
		newValue: string,
		e164: string,
		country: string
	) => {
		if ( onCountryDropdownClick ) {
			onCountryDropdownClick();
		}
		onValueChange( e164 );
		onValidationChange( validatePhoneNumber( e164, country ) );
	};

	return (
		<div
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
